<?php
/* --- See the Calendar Widget Documentation for more information: https://calendar.byu.edu/how-use-calendar-widget */
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


/* STEP 1. REQUIRED. Set Categories Here: --- */
$blockTitle = isset($_POST["title"]) ? strtolower(trim($_POST["title"])) : "Calendar Events";

$categories = isset($_POST["categories"]) ? strtolower(trim($_POST["categories"])) : "all";

$date = isset($_POST["days"]) ? intval($_POST["days"]) : 7;

$price = isset($_POST["price"]) ? strtolower(trim($_POST["price"])) : "";

$displayType = isset($_POST["display"]) ? intval($_POST["display"]) : 1;

/* startDate usually stays as today -- */
$startDate = date("Y-m-d");
$endDate   = date("Y-m-d", strtotime("now + " . $date . " days"));

$url = 'http://calendar-test.byu.edu/api/Events?event[min][date]=' . $startDate . '&event[max][date]=' . $endDate . '&categories=' . $categories . '&price=' . $price;

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

//$displayType = 1;
switch ($displayType) {
    case 1:  // list type, grouped by date
        $html = calendar_widget_d7_list_format($jsonArr, $startDate, $endDate);
        break;
//    case 2:  // Vertical tiles, limited to first 3
//        $html = calendar_widget_d7_vertical_tiles_limited($jsonArr, $startDate, $endDate);
//        break;
//    case 3:  // Horizontal tiles, limited to first 3
//        $html = calendar_widget_d7_hoizontal_tiles_limited($jsonArr, $startDate, $endDate);
//        break;
//    case 4:  // Vertical tiles, showing 3 in a slider
//        $html = calendar_widget_d7_vertical_tiles_slider($jsonArr, $startDate, $endDate);
//        break;
//    case 5:  // Horizontal tiles, showing 3 in a slider
//        $html = calendar_widget_d7_horizontal_tiles_slider($jsonArr, $startDate, $endDate);
//        break;
    default:
        $html = calendar_widget_d7_list_format($jsonArr, $startDate, $endDate);
        break;
}
print "<h2>" . $blockTitle . "</h2>";
print $html;



/* --------- block content formats --------- */

/* --
*  1. List format is default simple list format, listed by date groups. This is the default format.
 * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_list_format($jsonArr, $startDate, $endDate) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {

        $html = '<div class="startDate-' . $startDate . ' endDate-' . $endDate . ' calendar-widget-block display-list">';
//    $html = '<h3>' . $startDate . ' through ' . $endDate . '</h3><p>HEre is some text.</p><p>And here is some more text.</p>';
//
        $currentTime = new DateTime();
        $currentTime->setTimestamp(strtotime("now"));

        $first_item = true;

        foreach($jsonArr as $item) {
//    $html .= '<p>There is an event.<p>';
            $new_date = new DateTime();
            $new_date->setTimestamp(strtotime($item['StartDateTime']));
            // set date's timezone if needed

            if ($first_item) {
                $html .= '<div class="date-wrapper"><div class="date-day-number">' . date("j", strtotime($item['StartDateTime'])) . '</div><div class="date-text">' . date("M, l", strtotime($item['StartDateTime'])) . '</div></div>';
                $currentTime = $new_date;
                $first_item = false;
            }

            $diff = $currentTime->diff($new_date);

            if ($diff->format('%a') !== '0') {
//        $html .= '<h3><div class="date-text">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div></h3>';
                $html .= '<div class="date-wrapper"><div class="date-day-number">' . date("j", strtotime($item['StartDateTime'])) . '</div><div class="date-text">' . date("M, l", strtotime($item['StartDateTime'])) . '</div></div>';
                $currentTime = $new_date;
            }

            $html .='<div class="event">';

//    $html .= '<img src="' . $item['ImgUrl'] . '">';
            $html .= '<div class="event-content">';
            $html .= '<div class="event-title"><a href="' . $item['FullUrl'] . ' " target="_blank"><div class="title">' . $item['Title'] . '</div></a></div>';

            if ($item['AllDay'] == 'false') {
                $html .= '<div class="event-time">' . date("g:i A", strtotime($item['StartDateTime']));
                if ($item['EndDateTime'] != null) {
//          $html .= ' - ' . date("g:i A", strtotime($item['EndDateTime']));
                }
//        $html .= ' MT';
                $html .= '</div>';
            } else {
                $html .= '<div class="event-time">All Day</div>';
            }

            $item_id = uniqid();

            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }
    return $html;
}

/* --
*  2. Vertical Tiles Limited format shows 3 vertical tiles
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_vertical_tiles_limited($jsonArr, $startDate, $endDate) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {

        $html = '<div class="startDate-' . $startDate . ' endDate-' . $endDate . '">';
//    $html = '<h3>' . $startDate . ' through ' . $endDate . '</h3><p>HEre is some text.</p><p>And here is some more text.</p>';
//
        $currentTime = new DateTime();
        $currentTime->setTimestamp(strtotime("now"));

        $first_item = true;

        foreach($jsonArr as $item) {
//    $html .= '<p>There is an event.<p>';
            $new_date = new DateTime();
            $new_date->setTimestamp(strtotime($item['StartDateTime']));
            // set date's timezone if needed

            if ($first_item) {
                $html .= '<div class="date-wrapper"><div class="date-day-number">' . date("j", strtotime($item['StartDateTime'])) . '</div><div class="date">' . date("M, l", strtotime($item['StartDateTime'])) . '</div></div>';
                $currentTime = $new_date;
                $first_item = false;
            }

            $diff = $currentTime->diff($new_date);

            if ($diff->format('%a') !== '0') {
                $html .= '<h3><div class="date">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div></h3>';
                $currentTime = $new_date;
            }

            $html .='<div class="event">';

//    $html .= '<img src="' . $item['ImgUrl'] . '">';
            $html .= '<div class="event-content">';
            $html .= '<a href="' . $item['FullUrl'] . ' " target="_blank"><div class="title">' . $item['Title'] . '</div></a>';

            if ($item['AllDay'] == false) {
                $html .= '<div class="time">' . date("g:i A", strtotime($item['StartDateTime']));
                if ($item['EndDateTime'] != null) {
                    $html .= ' - ' . date("g:i A", strtotime($item['EndDateTime']));
                }
                $html .= ' MT </div>';
            } else {
                $html .= '<div class="time">All Day</div>';
            }

//      $item_id = uniqid();

            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }

    return $html;

}

/* --
*  3. Horizontal Tiles Limited format shows 3 horizontal tiles
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_hoizontal_tiles_limited($jsonArr, $startDate, $endDate) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {

        $html = '<div class="startDate-' . $startDate . ' endDate-' . $endDate . '">test: </div>';
//    $html = '<h3>' . $startDate . ' through ' . $endDate . '</h3><p>HEre is some text.</p><p>And here is some more text.</p>';
//
        $currentTime = new DateTime();
        $currentTime->setTimestamp(strtotime("now"));

        $first_item = true;

        foreach($jsonArr as $item) {
//    $html .= '<p>There is an event.<p>';
            $new_date = new DateTime();
            $new_date->setTimestamp(strtotime($item['StartDateTime']));
            // set date's timezone if needed

            if ($first_item) {
                $html .= '<div class="date-day-number">' . date("j", strtotime($item['StartDateTime'])) . '</div><div class="date">' . date("M, l", strtotime($item['StartDateTime'])) . '</div>';
                $currentTime = $new_date;
//        $first_item = false;
            }

            $diff = $currentTime->diff($new_date);

            if ($diff->format('%a') !== '0') {
                $html .= '<h3><div class="date">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div></h3>';
                $currentTime = $new_date;
            }

            $html .='<div class="event">';

//    $html .= '<img src="' . $item['ImgUrl'] . '">';
            $html .= '<div class="event-content">';
            $html .= '<a href="' . $item['FullUrl'] . ' " target="_blank"><div class="title">' . $item['Title'] . '</div></a>';

            if ($item['AllDay'] == false) {
                $html .= '<div class="time">' . date("g:i A", strtotime($item['StartDateTime']));
                if ($item['EndDateTime'] != null) {
                    $html .= ' - ' . date("g:i A", strtotime($item['EndDateTime']));
                }
                $html .= ' MT </div>';
            } else {
                $html .= '<div class="time">All Day</div>';
            }

//      $item_id = uniqid();

            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }

    return $html;

}



/* --
*  4. Vertical Tiles Slider format shows 3 vertical tiles, scrolling through a slider
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_vertical_tiles_slider($jsonArr, $startDate, $endDate) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {
// add content to html
    }

    return $html;

}

/* --
*  5. Horizontal Tiles Slider format shows 3 horizontal tiles, scrolling through a slider
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_hoizontal_tiles_slider($jsonArr, $startDate, $endDate) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {
// add content to html
    }

    return $html;

}


/* ========================= CSS ========================= */
$css=<<<CSS
<style>
/* -- General -- */
.calendar-block-title {
    font-family: "Sentinel A", "Sentinel B";
}
.block-calendar-widget div {
    font-family: "Gotham A", "Gotham B";
}
a {
    text-decoration: none;
    color: #003da5; // royal blue
}
a:hover, a:focus {
    text-decoration: none;
    color: #002c5c; // dark royal
}

/* ----------- List format -------- */
.block-calendar-widget {
    width: 289px;
}

.block-calendar-widget h2.block-title {
    border-bottom: 1px solid #e5e5e5;
}

.calendar-widget-block.display-list {
    width: 289px;
    margin-right: 20px;
}
@media screen and (max-width: 1023px) {
    .block-calendar-widget {
        width: 100%;
    }
    .calendar-widget-block.display-list {
        width: 100%;
        margin-right: 0px;
    }
}
.date-wrapper {
    display: flex;
    margin-bottom: 12px;
}
.date-day-number {
    font-family: "Sentinel A", "Sentinel B";
    font-weight: bold;
    font-size: 26px;
    margin-right: 7px;
}
.date-text {
    font-weight: 500;
    font-size: 21px;
    padding-top: 4px;

}


.event-content {
    padding: 0px 0px 15px 15px;
    display: flex;
    justify-content: space-between;
    line-height: 1.3em;
}

.event-title {

}
.event-time {
    min-width: 60px;
    margin-left: 12px;
    color: #767676;
    font-size: 14px;
    display: flex;
    justify-content: flex-end;
}


</style>
CSS;

echo $css;

?>