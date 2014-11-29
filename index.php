<?php
$locale = isset($_GET['locale'])? $_GET['locale'] : 'data';
$locale = htmlspecialchars(strip_tags($locale));
$label = $locale == 'data' ? 'All locales' : $locale;
?>
<!doctype html>
<html>
<head>
<meta charser="utf-8">
<title>Status of Key Web Parts Over Time</title>
<script src="dygraph-combined.js"></script>
    <style type='text/css'>
      #graphdiv .dygraph-legend > span { display: none; }
      #graphdiv .dygraph-legend > span.highlight { display: inline; }
    </style>
</head>
<body>
    <div id="graphdiv" style="width:1400px; height:700px;"></div>
    <script type="text/javascript">
    g2 = new Dygraph(
        document.getElementById("graphdiv"),
        "logs/<?=$locale?>.csv", // path to CSV file
        {
            gridLineColor: 'lightgray',
            highlightCircleSize: 5,
            strokeWidth: 2,
            ylabel: 'Missing strings',
            valueRange: [0, 1900],
            title: 'State of key web parts for: <?=$label;?>',
            fillGraph: true,
            strokeBorderWidth: 1,
            gridLinePattern: [2,2],
            highlightSeriesOpts: {
                  strokeWidth: 3,
                  strokeBorderWidth: 1,
                  highlightCircleSize: 5,
            }
        }
    );
    </script>
</body>
</html>
