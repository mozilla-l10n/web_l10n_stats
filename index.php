<?php
$locale = isset($_GET['locale'])? $_GET['locale'] : 'data';
?>
<!doctype html>
<html>
<head>
<meta charser="utf-8">
<script src="dygraph-combined.js"></script>
</head>
<body>
    <h3><?=$locale;?></h3>
    <div id="graphdiv"
    style="width:1400px; height:700px;"></div>
    <div id="status" style="width:100px; font-size:0.8em; padding-top:5px; position:absolute; top:0; right:0"></div>
    <script type="text/javascript">
    g2 = new Dygraph(
        document.getElementById("graphdiv"),
        "logs/<?=$locale?>.csv", // path to CSV file
        {
            valueRange: [0, 2000]
        }
    );
    </script>
</body>
</html>
