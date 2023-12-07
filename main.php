<?php

use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\DatabaseConnection\DatabaseConnection;
use PierreMiniggio\DatabaseFetcher\DatabaseFetcher;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

function getNumericMonthFromTurkishMonthName(string $turkishMonthName): ?int
{
    $mapping = [
        'OCAK' => 1,
        'ŞUBAT' => 2,
        'MART' => 3,
        'NİSAN' => 4,
        'MAYIS' => 5,
        'HAZİRAN' => 6,
        'TEMMUZ' => 7,
        'AĞUSTOS' => 8,
        'EYLÜL' => 9,
        'EKİM' => 10,
        'KASIM' => 11,
        'ARALIK' => 12
    ];

    foreach ($mapping as $turkishMonthToCheck => $numericValue) {
        if ($turkishMonthName === $turkishMonthToCheck) {
            return $numericValue;
        }
    }

    return null;
}

$request = curl_init('https://trafik.gov.tr/kgys-goruntuleri');

curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => 1
]);

$response = curl_exec($request);
$error = curl_error($request);
curl_close($request);

if (! $response) {
    echo json_encode(['error' => 'No response, error : ' . $error]);
    die;
}

$tableStart = '<table align="left" border="1"';

$explodeOnTableStart = explode($tableStart, $response);

if (count($explodeOnTableStart) < 2) {
    echo json_encode(['error' => 'Fail to split on table start']);
    die;
}

$tableEnd = '</table>';

$turkishMonthStart = '<span style="color:#e74c3c;">';
$turkishMonthEnd = '<';

$videoLinkStart = '<a href="';
$videoLinkEnd = '"';

$monthsAndVideoLinks = [];

foreach ($explodeOnTableStart as $explodedItemIndexOnTableStart => $explodedItemOnTableStart) {
    if (! $explodedItemIndexOnTableStart) {
        continue;
    }

    $explodeOnTableEnd = explode($tableEnd, $explodedItemOnTableStart, 2);

    if (count($explodeOnTableEnd) !== 2) {
        echo json_encode(['error' => 'Fail to explode on table end']);
        die;
    }

    $table = $tableStart . $explodeOnTableEnd[0] . $tableEnd;

    $explodeOnTurkishMonthStart = explode($turkishMonthStart, $table, 2);

    if (count($explodeOnTurkishMonthStart) !== 2) {
        echo json_encode(['error' => 'Fail to explode on turkish month start']);
        die;
    }

    $explodeOnTurkishMonthEnd = explode($turkishMonthEnd, $explodeOnTurkishMonthStart[1], 2);

    if (count($explodeOnTurkishMonthEnd) !== 2) {
        echo json_encode(['error' => 'Fail to explode on turkish month end']);
        die;
    }

    $turkishMonthYear = strtoupper(html_entity_decode($explodeOnTurkishMonthEnd[0]));

    $explodeTurkishMonthYearOnSpace = explode(' ', $turkishMonthYear);

    $monthNumber = getNumericMonthFromTurkishMonthName($explodeTurkishMonthYearOnSpace[0]);

    if ($monthNumber === null) {
        echo json_encode(['error' => 'Month couldn\'t be read']);
        die;
    }

    $monthYear = $explodeTurkishMonthYearOnSpace[1] . '-' . str_pad($monthNumber, 2, '0', STR_PAD_LEFT);

    $explodeOnVideoLinkStart = explode($videoLinkStart, $table);

    $videoLinks = [];

    foreach ($explodeOnVideoLinkStart as $explodedItemOnVideoLinkStartIndex => $explodedItemOnVideoLinkStart) {
        if ($explodedItemOnVideoLinkStartIndex === 0) {
            continue;
        }

        $explodeOnVideoLinkEnd = explode($videoLinkEnd, $explodedItemOnVideoLinkStart, 2);

        if (count($explodeOnVideoLinkEnd) !== 2) {
            echo json_encode(['error' => 'Fail to explode on video link end']);
            die;
        }

        $relativeVideoLink = $explodeOnVideoLinkEnd[0];

        $videoLinks[] = 'https://trafik.gov.tr' . $relativeVideoLink;
    }

    sort($videoLinks);
    $monthsAndVideoLinks[$monthYear] = $videoLinks;
}

ksort($monthsAndVideoLinks);


$configProvider = new ConfigProvider(__DIR__ . DIRECTORY_SEPARATOR);
$config = $configProvider->get();
$dbConfig = $config;

$fetcher = new DatabaseFetcher(new DatabaseConnection(
    $dbConfig['host'],
    $dbConfig['database'],
    $dbConfig['username'],
    $dbConfig['password'],
    DatabaseConnection::UTF8_MB4
));

$getPeriodId = function (string $monthYear) use ($fetcher): ?int
{
    $periods = $fetcher->query(
        $fetcher->createQuery(
            'crash_period'
        )->select(
            'id'
        )->where(
            'period = :period'
        ),
        ['period' => $monthYear]
    );

    if (count($periods)) {
        return $periods[0]['id'];
    }

    return null;
};

$getVideoId = function (int $crashPeriodId, string $videoLink) use ($fetcher): ?int
{
    $videos = $fetcher->query(
        $fetcher->createQuery(
            'crash'
        )->select(
            'id'
        )->where(
            'crash_period_id = :crash_period_id AND video_link = :video_link'
        ),
        [
            'crash_period_id' => $crashPeriodId,
            'video_link' => $videoLink
        ]
    );

    if (count($videos)) {
        return $videos[0]['id'];
    }

    return null;
};

foreach ($monthsAndVideoLinks as $monthYear => $videoLinks) {
    $periodId = $getPeriodId($monthYear);

    if (! $periodId) {
        $fetcher->exec(
            $fetcher->createQuery(
                'crash_period'
            )->insertInto(
                'period',
                ':period'
            ),
            ['period' => $monthYear]
        );

        $periodId = $getPeriodId($monthYear);

        if (! $periodId) {
            throw new Exception('Error while inserting period');
        }
    }

    foreach ($videoLinks as $videoLink) {
        $videoId = $getVideoId($periodId, $videoLink);

        if (! $videoId) {
            $fetcher->exec(
                $fetcher->createQuery(
                    'crash'
                )->insertInto(
                    'crash_period_id, video_link',
                    ':crash_period_id, :video_link'
                ),
                [
                    'crash_period_id' => $periodId,
                    'video_link' => $videoLink
                ]
            );

            $videoId = $getVideoId($periodId, $videoLink);

            if (! $videoId) {
                throw new Exception('Error while inserting video');
            }
        }
    }
}
