<?php
    function getImages($url, $page) {
        $images = [];

        if (preg_match_all('!<\s*img.*?src=[\'"](.*?)[\'"].*?>!', $page, $matches)) {
            $images = array_unique($matches[1]);
            $parsedUrl = parse_url($url);

            foreach ($images as &$image) {
                if (!preg_match('!^\w*://!', $image)) {
                    $image = "$parsedUrl[scheme]://$parsedUrl[host]$image";
                }
            }
            unset($image);
        }

        return $images;
    }

    function getPage($url) {
        $page = file_get_contents($url);
        if (!$page) throw new \Exception('Страница не прочитана');

        return $page;
    }

    function imageCurl($image) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $image);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($curl, CURLOPT_NOBODY, true);

        return $curl;
    }

    function execImagesCurl($curls) {
        $multiCurl = curl_multi_init();

        foreach ($curls as $curl) curl_multi_add_handle($multiCurl, $curl);

        do {
            $status = curl_multi_exec($multiCurl, $active);
            if ($active) curl_multi_select($multiCurl);
            $info = curl_multi_info_read($multiCurl);
        } while ($active && $status == CURLM_OK);

        foreach ($curls as $curl) curl_multi_remove_handle($multiCurl, $curl);
        curl_multi_close($multiCurl);
    }

    function fetchImagesSizes($curls) {
        $sizes = [];

        foreach ($curls as $image => $curl) {
            $sizes[$image] = max(0, curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T));
        }

        return $sizes;
    }

    function humanizeSize($size) {
        $factors = ['T', 'G', 'M', 'K'];
        $multiplier = pow(1024, count($factors));

        while ($multiplier > 1) {
            if ($size >= $multiplier) {
                return number_format($size / $multiplier, 2) . current($factors);
            }

            $multiplier /= 1024;
            next($factors);
        }

        return $size;
    }

    $images = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $url = $_POST['url'];
            $page = getPage($url);
            $images = getImages($url, $page);

            $curls = [];
            foreach ($images as $image) $curls[$image] = imageCurl($image);
            execImagesCurl($curls);

            $sizes = fetchImagesSizes($curls);
            foreach ($curls as $curl) curl_close($curl);

            $imagesCount = count($images);
            $imagesSize = array_sum($sizes);
        } catch(\Exception $e) {
            $error = $e->getMessage();
        }
    }
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8" />
        <style>
            .error {color: red;}
            .images {width: 100%;}
            .images td {width: 25%;}
            .images td img {width: 100%;}
        </style>
    </head>
    <body>
        <?php if ($error) : ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <?php if (isset($images)) : ?>
            <a href="">Назад</a>
            <p>Изображения с <?= $_POST['url'] ?></p>

            <?php if ($images) : ?>
                <table class="images">
                    <tbody>
                        <?php while ($images) : ?>
                            <?php $row = array_splice($images, 0, 4) ?>
                            <tr>
                                <?php foreach ($row as $image) : ?>
                                    <td><img src="<?= htmlspecialchars($image) ?>"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <p>Всего изображений: <?= $imagesCount ?></p>
                <p>Общий вес: <?= humanizeSize($imagesSize) ?></p>
            <?php else : ?>
                <p>Изображения не найдены</p>
            <?php endif; ?>
        <?php else : ?>
            <form method="POST">
                <input type="text" name="url" />
                <button>Go</button>
            </form>
        <?php endif; ?>
    </body>
</html>
