<?php

namespace App\Command;

use App\Entity\Artist;
use App\Entity\Track;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'parser:artist',
    description: 'Парсинг информации об артисте, его альбомах и треках с Яндекс Музыки',
)]
class ParserArtistCommand extends Command
{
    private SymfonyStyle $io;
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    private int $artist_id;
    private object $raw_json_data;

    private const LINK_ARTIST_ALBUM_HTML = 0;
    private const LINK_ARTIST_JSON = 1;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient, string $name = null)
    {
        parent::__construct($name);
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('yandex_music_artist_link', InputArgument::REQUIRED, 'Ссылка на страницу артиста')
            ->addOption('id', 'i', null, 'С этим ключом можно указать только ID артиста');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $artist_link = $input->getArgument('yandex_music_artist_link');

        if ($input->getOption('id') && !$this->isCorrectID($artist_link)) {
            $this->io->error('ID исполнителя может содержать только цифры');
            return Command::FAILURE;
        }

        if (!$this->isLinkCorrect($artist_link) && !$input->getOption('id')) {
            $this->io->error('Неправильный формат ссылки на исполнителя');
            return Command::FAILURE;
        }

        if(!$this->getRawJSONData()) {
            $this->io->error('Ошибка при скачивании файла JSON');
            return Command::FAILURE;
        }

        $counter = 1;
        foreach ($this->raw_json_data->tracks as $track) {
            echo sprintf("\rОбработка %s трека из %s", $counter++, count($this->raw_json_data->tracks));

            $this->addTrackToDatabase($track);
        }

        $this->io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }

    private function isCorrectID($artist_id): bool
    {
        if (!preg_match('/^\d+$/', $artist_id, $matches)) {
            return false;
        }

        $this->artist_id = $matches[0];

        return true;
    }

    private function isLinkCorrect(string $artist_link): bool
    {
        $matches = [];

        if (!preg_match('/https?:\/\/music\.yandex\.ru\/artist\/(\d+)(\/tracks)?/i', $artist_link, $matches)) {
            return false;
        }

        $this->artist_id = $matches[1];

        return true;
    }

    private function isCorrectJSON(string $data): bool
    {
        if (!empty($data)) {
            return is_string($data) && is_array(json_decode($data, true));
        }

        return false;
    }

    private function getFullLink(int $link_type): string|null
    {
        switch ($link_type) {
            case self::LINK_ARTIST_ALBUM_HTML:
                return sprintf('https://music.yandex.ru/artist/%s/tracks', $this->artist_id);
            case self::LINK_ARTIST_JSON:
                return 'https://music.yandex.ru/handlers/artist.jsx';
        }

        return null;
    }

    private function getRawJSONData(): bool
    {
        try {
            $content = $this->httpClient->request('GET', $this->getFullLink(self::LINK_ARTIST_JSON),
                [
                    'headers' => [
                        'Accept' => 'application/json, text/javascript',
                        'Referer' => 'https://music.yandex.ru/artist/'.$this->artist_id
                    ],
                    'query' => [
                        'artist' => $this->artist_id,
                        'what' => 'tracks',
                        'period' => 'month',
                        'trackPageSize' => 1000,
                        'lang' => 'ru',
                        'overembed' => false
                    ]
                ])->getContent();
        } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            $this->io->error($e->getMessage());
            return false;
        }

        if(!$this->isCorrectJSON($content)) {
            return false;
        }

        $this->raw_json_data = json_decode($content);

        // Если у Яндекса сработал антипарсинг, то они будут возвращать
        // страницу с ошибкой. В этом случае, дальше обработку не производим.
        if(!is_object($this->raw_json_data)) {
            return false;
        }

        return true;
    }

    private function getArtist(int $artist_id): Artist|bool
    {
        $artist = $this->entityManager->getRepository(Artist::class)->find($artist_id);

        if($artist instanceof Artist) {
            return $artist;
        }

        // Может быть, поможет от интипарсеров, но не уверен, что это сработает
        sleep(rand(1,10));

        try {
            $content = $this->httpClient->request('GET', $this->getFullLink(self::LINK_ARTIST_JSON),
                [
                    'headers' => [
                        'Accept' => 'application/json, text/javascript',
                        'Referer' => 'https://music.yandex.ru/artist/'.$artist_id
                    ],
                    'query' => [
                        'artist' => $artist_id,
                        'period' => 'month',
                        'trackPageSize' => 10,
                        'lang' => 'ru',
                        'overembed' => false
                    ]
                ])->getContent();
        } catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface | TransportExceptionInterface $e) {
            $this->io->error($e->getMessage());
            return false;
        }

        $content = json_decode($content);

        if(!is_object($content)) {
            return false;
        }

        $artist = new Artist();
        $artist->setId($artist_id);
        $artist->setName($content->artist->name);
        $artist->setAlbumsCount($content->artist->counts->directAlbums);
        $artist->setFollowersCount($content->artist->likesCount);
        $artist->setListenersCount($content->stats->lastMonthListeners);

        $this->entityManager->persist($artist);
        $this->entityManager->flush();

        return $artist;
    }

    private function addTrackToDatabase(object $track_data)
    {
        $track = $this->entityManager->getRepository(Track::class)->find($track_data->id);

        // Если трек с таким ID уже есть в БД, то переходим к следующему
        if($track instanceof Track) {
            return;
        }

        $track = new Track();
        $track->setId($track_data->id);
        $track->setTitle($track_data->title);
        $track->setDuration($track_data->durationMs);

        foreach ($track_data->artists as $artist) {
            $sideArtist = $this->getArtist($artist->id);

            // Если по какой-то причине произошёл сбой и метод не вернул артиста
            // то пропускаем трек. В данном случае можно было бы делать некую
            // очередь из треков, которые обработались с ошибкой, чтобы можно
            // было есть в другое время отдельно обработать.
            if(!$sideArtist instanceof Artist) {
                return;
            }

            $track->addArtist($sideArtist);
        }

        $this->entityManager->persist($track);
        $this->entityManager->flush();
    }
}
