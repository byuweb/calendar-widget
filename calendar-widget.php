<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

date_default_timezone_set('America/Denver');

function truncate($text, $length = 100, $options = array()) {
    $default = array(
        'ending' => '', 'exact' => true, 'html' => false
    );
    $options = array_merge($default, $options);
    extract($options);

    if ($html) {
        if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        $totalLength = mb_strlen(strip_tags($ending));
        $openTags = array();
        $truncate = '';

        preg_match_all('/(<\/?([\w+]+)[^>]*>)?([^<>]*)/', $text, $tags, PREG_SET_ORDER);
        foreach ($tags as $tag) {
            if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2])) {
                if (preg_match('/<[\w]+[^>]*>/s', $tag[0])) {
                    array_unshift($openTags, $tag[2]);
                } else if (preg_match('/<\/([\w]+)[^>]*>/s', $tag[0], $closeTag)) {
                    $pos = array_search($closeTag[1], $openTags);
                    if ($pos !== false) {
                        array_splice($openTags, $pos, 1);
                    }
                }
            }
            $truncate .= $tag[1];

            $contentLength = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', ' ', $tag[3]));
            if ($contentLength + $totalLength > $length) {
                $left = $length - $totalLength;
                $entitiesLength = 0;
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i', $tag[3], $entities, PREG_OFFSET_CAPTURE)) {
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitiesLength <= $left) {
                            $left--;
                            $entitiesLength += mb_strlen($entity[0]);
                        } else {
                            break;
                        }
                    }
                }

                $truncate .= mb_substr($tag[3], 0 , $left + $entitiesLength);
                break;
            } else {
                $truncate .= $tag[3];
                $totalLength += $contentLength;
            }
            if ($totalLength >= $length) {
                break;
            }
        }
    } else {
        if (mb_strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = mb_substr($text, 0, $length - mb_strlen($ending));
        }
    }
    if (!$exact) {
        $spacepos = mb_strrpos($truncate, ' ');
        if (isset($spacepos)) {
            if ($html) {
                $bits = mb_substr($truncate, $spacepos);
                preg_match_all('/<\/([a-z]+)>/', $bits, $droppedTags, PREG_SET_ORDER);
                if (!empty($droppedTags)) {
                    foreach ($droppedTags as $closingTag) {
                        if (!in_array($closingTag[1], $openTags)) {
                            array_unshift($openTags, $closingTag[1]);
                        }
                    }
                }
            }
            $truncate = mb_substr($truncate, 0, $spacepos);
        }
    }
    $truncate .= $ending;

    if ($html) {
        foreach ($openTags as $tag) {
            $truncate .= '</'.$tag.'>';
        }
    }

    return $truncate;
}

$unique = uniqid('output-div-');
$_POST = json_decode(file_get_contents('php://input'), true);

$cats = isset($_POST["categories"]) ? strtolower(trim($_POST["categories"])) : "4";
$date = isset($_POST["days"]) ? intval($_POST["days"]) : 7;

$today = date("n-j-Y");
$end   = date("n-j-Y", strtotime("now + " . $date . " days"));

$url = 'http://calendar.byu.edu/api/Events?startdate=' . $today . '&enddate=' . $end . '&categories=' . $cats;
//$url = 'http://calendar.byu.edu/api/Events?startdate=' . $today . '&enddate=' . $end . '&categories=' . $cats;

$options = array(
    'http' => array(
        'method' => "GET",
        'header' => "Accept: application/json\r\n"
    )
);

$context = stream_context_create($options);

$result = file_get_contents($url, false, $context);

$jsonArr = json_decode($result, true);

$html = "";

$curr_date = new DateTime();
$curr_date->setTimestamp(strtotime("now"));

$first_item = true;

foreach($jsonArr as $item) {
    $new_date = new DateTime();
    $new_date->setTimestamp(strtotime($item['StartDateTime']));

    if ($first_item) {
        $html .= '<div class="date">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div>';
        $curr_date = $new_date;
        $first_item = false;
    }

    $diff = $curr_date->diff($new_date);

    if ($diff->format('%a') !== '0') {
        $html .= '<div class="date">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div>';
        $curr_date = $new_date;
    }

    $html .='<div class="event">';

    $html .= '<img src="http://calendar.byu.edu' . $item['ImgUrl'] . '">';
    $html .= '<div class="event-content">';
    $html .= '<div class="title">' . $item['Title'] . '</div>';

    if ($item['AllDay'] == false) {
        $html .= '<div class="time">' . date("g:i A", strtotime($item['StartDateTime']));
        if ($item['EndDateTime'] != null) {
            $html .= ' - ' . date("g:i A", strtotime($item['EndDateTime']));
        }
        $html .= ' MT </div>';
    } else {
        $html .= '<div class="time">All Day</div>';
    }
    if ($item['LocationName'] != null) {
        $html .= '<div class="location">' . $item['LocationName'] . ' - <a href="http://maps.google.com/?q=' . $item['Latitude'] . ',' . $item['Longitude'] . '&z=18" target="_blank">View Map</a></div>';
    }
    if ($item['LowPrice'] == 0.0 && $item['LowPrice'] != null) {
        $html .= '<div class="price">FREE</div>';
    } else if ($item['LowPrice'] == null) {
        $html .= '';
    } else {
        $html .= '<div class="price">$' . $item['LowPrice'];
        if ($item['HighPrice'] != null) {
            $html .= ' - $' . $item['HighPrice'];
        }
        $html .= '</div>';
    }
    $item_id = uniqid();
    if (strlen($item['Description']) <= 100) {
        $html .= '<div class="description" data-id="' . $item_id . '">' . $item['Description'] . '<br><a href="' . $item['MoreInformationUrl'] . '">More Information</a><br><a href="http://calendar.byu.edu/iCal/Event/' . $item['EventId'] . '">Download iCal</a></div>';
    } else {
        $partial_description = truncate($item['Description'], 100);
        $html .= '<div class="partial-description show" data-id="' . $item_id . '">' . $partial_description . '... <a href="#" class="see-more" data-id="' . $item_id . '">See More</a></div>';
        $html .= '<div class="description hidden" data-id="' . $item_id . '">' . $item['Description'] . '<br><a href="' . $item['MoreInformationUrl'] . '">More Information</a><br><a href="http://calendar.byu.edu/iCal/Event/' . $item['EventId'] . '">Download iCal</a></div>';
    }
    $html .= '</div>';
    $html .= '</div>';
}

echo $html;

/* ========================= CSS ========================= */
$css=<<<CSS
<style>
.calendar-wrapper {
    width: 100%;
    margin: 0;
    font-family: "Whitney SSm A", " Whitney SSm B","OpenSans", "sans-serif", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Helvetica, Arial, sans-serif; 
    background-color: white;
    box-sizing: border-box;
}
.calendar-wrapper a {
    color: #369;
    text-decoration: none;
}
.calendar-wrapper a:hover {
    text-decoration: underline;
}
.date {
    box-sizing: border-box;
    padding: 10px;
    background-color: #628cb6;
    width: 100%;
    color: #fff;
    font: 1.3em/1.2 "Lucida Grande","Lucida Sans Unicode","Lucida Sans",Helvetica,Arial,sans-serif;
    background-image: -webkit-linear-gradient(top,#628cb6 0,#369 100%);
}
.title {
    color: #369; 
    line-height:1.3em; 
    font-size: 1.1em; 
    font-family: "Whitney SSm A", " Whitney SSm B","OpenSans", "sans-serif", "Lucida Grande", "Lucida Sans unicode", "Lucida Sans", Helvetica, Arial, sans-serif; 
    font-weight: 600;
    display: block;
    width: 80%;
}
.event {
    margin: 20px 10px;
}
.event-content {
    margin: 0 20px;
    display: inline-block;
    width: 75%;
}
.time {
    width: 80%;
    font-size: .9em; 
    color: #444; 
    margin:0; 
    font-weight: bold; 
    font-family: "Whitney SSm A", " Whitney SSm B","OpenSans", "Thonburi", "DroidSans", "Droid Sans", "sans-serif", "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Helvetica, Arial, sans-serif; 
    display:block;
}
.price {
    color:green;
    text-transform: uppercase;
}
.hidden {
    display: none;
}
.show {
    display: inline-block;
}
img {
    margin: 0 20px 20px;
    display: inline-block;
    width: 150px; 
    max-height: 100px;
    vertical-align: top;
}
</style>
CSS;

echo $css;

?>