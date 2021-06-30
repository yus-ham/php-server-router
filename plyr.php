<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Plyr - A simple, customizable HTML5 Video, Audio, YouTube and Vimeo player</title>
  <meta name="description" property="og:description" content="A simple HTML5 media player with custom controls and WebVTT captions." />
  <meta name="author" content="Sam Potts" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Icons -->
  <link rel="icon" href="https://cdn.plyr.io/static/icons/favicon.ico" />
  <link rel="icon" type="image/png" href="https://cdn.plyr.io/static/icons/32x32.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="https://cdn.plyr.io/static/icons/16x16.png" sizes="16x16" />
  <link rel="apple-touch-icon" sizes="180x180" href="https://cdn.plyr.io/static/icons/180x180.png" />

  <!-- Open Graph -->
  <meta property="og:title" content="Plyr - A simple, customizable HTML5 Video, Audio, YouTube and Vimeo player" />
  <meta property="og:site_name" content="Plyr" />
  <meta property="og:url" content="https://plyr.io" />
  <meta property="og:image" content="https://cdn.plyr.io/static/icons/1200x630.png" />

  <!-- Twitter -->
  <meta name="twitter:card" content="summary" />
  <meta name="twitter:site" content="@sam_potts" />
  <meta name="twitter:creator" content="@sam_potts" />
  <meta name="twitter:card" content="summary_large_image" />

  <!-- Docs styles -->
  <link rel="stylesheet" href="?view=plyr/css/demo.css" />

  <!-- Preload -->
  <link rel="preload" as="font" crossorigin type="font/woff2" href="https://cdn.plyr.io/static/fonts/gordita-medium.woff2" />
  <link rel="preload" as="font" crossorigin type="font/woff2" href="https://cdn.plyr.io/static/fonts/gordita-bold.woff2" />

</head>

<body>
  <div class="grid">
    <header>
      <h1>Pl<span>a</span>y<span>e</span>r</h1>



    </header>
    <main>
      <div id="container">
        <video id="player" playsinline controls data-poster="/path/to/poster.jpg">
          <source src="<?= $_GET['view'] ?>" type="video/mp4" />
          <source src="/path/to/video.webm" type="video/webm" />

          <!-- Captions are optional -->
          <track kind="captions" label="English captions" src="/path/to/captions.vtt" srclang="en" default />
        </video>
      </div>
    </main>
  </div>

  <aside>
  </aside>

  <script src="?view=plyr/js/demo.js" crossorigin="anonymous"></script>
</body>

</html>
