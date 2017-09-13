<?php
/* --- See the Calendar Widget Documentation for more information: https://calendar.byu.edu/how-use-calendar-widget */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

date_default_timezone_set('America/Denver');

$unique = uniqid('output-div-');
$_POST = json_decode(file_get_contents('php://input'), true);

/* STEP 1. REQUIRED. Set Categories Here: --- */
$blockTitle = isset($_POST["title"]) ? trim($_POST["title"]) : "Calendar Events";

$categories = isset($_POST["categories"]) ? strtolower(trim($_POST["categories"])) : "all";

$date = isset($_POST["days"]) ? intval($_POST["days"]) : 7;

$price = isset($_POST["price"]) ? strtolower(trim($_POST["price"])) : "";

$displayType = isset($_POST["display"]) ? intval($_POST["display"]) : 1;

$limit = isset($_POST["limit"]) ? intval($_POST["limit"]) : "0"; // default to no limit
// using 0 to represent no limit for the interface side, to avoid confusion of putting -1
if($limit == 0) {
    $limit = "-1"; // counter will never hit -1, no limit
}

/* startDate usually stays as today -- */
$startDate = date("Y-m-d");
$endDate   = date("Y-m-d", strtotime("now + " . $date . " days"));

$url = 'https://calendar.byu.edu/api/Events?event[min][date]=' . $startDate . '&event[max][date]=' . $endDate . '&categories=' . $categories . '&price=' . $price;

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
        $html = calendar_widget_d7_list_format($jsonArr, $startDate, $endDate, $limit);
        break;
    case 2:  // Vertical tiles, limited to first 3
        $html = calendar_widget_d7_vertical_tiles_limited($jsonArr, $startDate, $endDate, $limit);
        break;
    case 3:  // Horizontal tiles, limited to first 3
        $html = calendar_widget_d7_horizontal_tiles_limited($jsonArr, $startDate, $endDate, $limit);
        break;
    case 4:  // Full page Calendar rows
        $html = calendar_widget_d7_fullpage_rows($jsonArr, $startDate, $endDate, $limit);
        break;
    case 5:  // Full page Calendar rows with Image
        $html = calendar_widget_d7_fullpage_image_rows($jsonArr, $startDate, $endDate, $limit);
        break;
    default:
        $html = calendar_widget_d7_list_format($jsonArr, $startDate, $endDate, $limit);
        break;
}
/* include calendar tile component for those display options */
print '<script src="//cdn.byu.edu/byu-calendar-tile/unstable/components.js"></script>';
print '<h2 class="calendar-block-title">' . $blockTitle . "</h2>";
print $html;



/* --------- block content formats --------- */

/* --
*  1. List format is default simple list format, listed by date groups. This is the default format.
 * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_list_format($jsonArr, $startDate, $endDate, $limit) {

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
        $count = 0;
        foreach($jsonArr as $item) {
            if($count == $limit) break;
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
            $count++;
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }
    return $html;
}

/* --
*  2. Vertical Tiles Limited format shows 3 vertical tiles
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_vertical_tiles_limited($jsonArr, $startDate, $endDate, $limit) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {
        if($limit == -1) {
            $limit = 3;
        }
//    $limit = 3;
        $html = '<div class="tile-container startDate-' . $startDate . ' endDate-' . $endDate . '" style="display: flex; flex-wrap: wrap; margin: 20px 0px;">';
//    $html .= '<p>the limit is ' . $limit . '</p>';
        $count = 0;
        foreach($jsonArr as $item) {
            if($count == $limit) break;
            $html .= '<byu-calendar-tile layout="vertical">';
            $html .= '<p slot="date" >' . date("Y-m-d", strtotime($item['StartDateTime'])) . '</p>';
            $html .= '<a href="' . $item['FullUrl'] . ' " slot="title" target="_blank"><div class="title">' . $item['Title'] . '</div></a>';


            if ($item['AllDay'] == 'false') {
                $html .= '<div class="time" slot="time">' . date("g:i A", strtotime($item['StartDateTime'])) . ' ' . $item['Timezone'] . '</div>';
            } else {
                $html .= '<div class="time" slot="time">All Day</div>';
            }

            if ($item['LocationName'] != null) {
                $html .= '<div class="location" slot="location">' . $item['LocationName'] . '</div>';
            }
            $html .= '</byu-calendar-tile>';
//Testing dummy content:
//        $html .= '<byu-calendar-tile layout="vertical">  <p slot="date">2017-02-15</p> 	<a href="www.google.com" slot="title">My Event Title</a><p slot="time">7:00 PM</p>	<p slot="location">Wilkinson Ballroom</p></byu-calendar-tile>';
            $count++;

        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }

    return $html;

}

/* --
*  3. Horizontal Tiles Limited format shows 3 horizontal tiles
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_horizontal_tiles_limited($jsonArr, $startDate, $endDate, $limit) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {

        $html = '<div class="tile-container startDate-' . $startDate . ' endDate-' . $endDate . '" style="display: flex; flex-wrap: wrap; margin: 20px 0px;">';
//    $html .= '<p>the limit is ' . $limit . '</p>';
        $count = 0;
        foreach($jsonArr as $item) {
            if($count == $limit) break;
            $html .= '<byu-calendar-tile layout="horizontal">';
            $html .= '<p slot="date" >' . date("Y-m-d", strtotime($item['StartDateTime'])) . '</p>';
            $html .= '<a href="' . $item['FullUrl'] . ' " slot="title" target="_blank"><div class="title">' . $item['Title'] . '</div></a>';
            if ($item['AllDay'] == 'false') {
                $html .= '<div class="time" slot="time">' . date("g:i A", strtotime($item['StartDateTime'])) . ' ' . $item['Timezone'] . '</div>';
            } else {
                $html .= '<div class="time" slot="time">All Day</div>';
            }
//      print_r($item['Description']);
            if ($item['ShortDescription'] != 'null') {
                $html .= '<p slot="description">' . $item['ShortDescription'] . '</p>';
            }
            if ($item['LocationName'] != null) {
                $html .= '<div class="location" slot="location">' . $item['LocationName'] . '</div>';
            }
            $html .= '</byu-calendar-tile>';
            $count++;
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }

    return $html;

}


/* --
*  4. Full page calendar display with rows
 *  * It takes the array of json, the startDate and the endDate
 */
function calendar_widget_d7_fullpage_rows($jsonArr, $startDate, $endDate, $limit) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {

        $html = '<div class="tile-container startDate-' . $startDate . ' endDate-' . $endDate . '">';
//    $html .= '<p>the limit is ' . $limit . '</p>';
        $count = 0;
        foreach($jsonArr as $item) {
            if($count == $limit) break;
            $html .= '<byu-calendar-row type="tile">';
            $html .= '<p slot="date" >' . date("Y-m-d", strtotime($item['StartDateTime'])) . '</p>';
            $html .= '<a href="' . $item['FullUrl'] . ' " slot="title" target="_blank">' . $item['Title'] . '</a>';
            if ($item['AllDay'] == 'false') {
                $html .= '<div class="time" slot="time">' . date("g:i A", strtotime($item['StartDateTime'])). ' ' . $item['Timezone'] . '</div>';
            } else {
                $html .= '<div class="time" slot="time">All Day</div>';
            }
            if ($item['LocationName'] != null) {
                $html .= '<div class="location" slot="location">' . $item['LocationName'] . '</div>';
            }

            // pricing and tickets
            if($item['TicketsExist'] == 'Yes') {

                if($item['IsFree'] == 'true') {
                    $html .= '<p slot="price">Free</p>';
                    if (!empty($item['TicketsUrl'])) {
                        $html .= '<a slot="tickets-link" target="_blank" href="' . $item['TicketsUrl'] . '">FREE TICKETS</a>';
                    }
                } else { // price or range
                    if (!empty($item['HighPrice'])) {
                        $html .= '<p slot="price">Tickets: $' . $item['LowPrice'] . ' - $' . $item['HighPrice'] . '</p>';
                    } else {
                        $html .= '<p slot="price">Tickets: $' . $item['LowPrice'] . '</p>';
                    }

                    if (!empty($item['TicketsUrl'])) {
                        $html .= '<a slot="tickets-link" target="_blank" href="' . $item['TicketsUrl'] . '">TICKETS</a>';
                    }
                }
            }

            $html .= '<a href="' . $item['FullUrl'] . '" slot="link" target="_blank">SEE FULL EVENT</a>';



            $html .= '</byu-calendar-row>';
            $count++;
        }
        $html .= '</div>'; // ending the wrapping div with start and end date classes
    }

    return $html;

}


/* --
*  5. Full page calendar display with rows WITH images, grouped by date. Includes price / ticket info.
 *  * It takes the array of json, the startDate and the endDate
 */


function calendar_widget_d7_fullpage_image_rows($jsonArr, $startDate, $endDate, $limit) {

    if (empty($jsonArr)) {
        // list is empty.
        $html = "<h3>No events.</h3>";
    } else {
        $html = "";
//    $html = '<div class="startDate-' . $startDate . ' endDate-' . $endDate . ' calendar-widget-block display-list">';
//    $html = '<h3>' . $startDate . ' through ' . $endDate . '</h3><p>HEre is some text.</p><p>And here is some more text.</p>';
//
//    $html .= '<p>the limit is ' . $limit . '</p>';
        $currentTime = new DateTime();
        $currentTime->setTimestamp(strtotime("now"));

        $first_item = true;
        $count = 0;
        foreach($jsonArr as $item) {
            if($count == $limit) break;
//    $html .= '<p>There is an event.<p>';
            $new_date = new DateTime();
            $new_date->setTimestamp(strtotime($item['StartDateTime']));
            // set date's timezone if needed

            if ($first_item) {
                $html .= '<div class="fullpage-date-wrapper"><div class="fullpage-date-weekday">' . date("l", strtotime($item['StartDateTime'])) . ' | ' . '</div><div class="fullpage-date-text">' . date("F j, Y", strtotime($item['StartDateTime'])) . '</div></div>';
                $currentTime = $new_date;
                $first_item = false;
            }

            $diff = $currentTime->diff($new_date);

            if ($diff->format('%a') !== '0') {
//        $html .= '<h3><div class="date-text">' . date("l, F j", strtotime($item['StartDateTime'])) . '</div></h3>';
                $html .= '<div class="fullpage-date-wrapper"><div class="fullpage-date-weekday">' . date("l", strtotime($item['StartDateTime'])) . ' | ' . '</div><div class="fullpage-date-text">' . date("F j, Y", strtotime($item['StartDateTime'])) . '</div></div>';
                $currentTime = $new_date;
            }

            $html .= '<byu-calendar-row type="image">';
//      $html .= '<p slot="date" >' . date("Y-m-d", strtotime($item['StartDateTime'])) . '</p>';

            $html .= '<img slot="image" src="' . $item['ImgUrl'] . '">';
            $html .= '<a href="' . $item['FullUrl'] . ' " slot="title" target="_blank">' . $item['Title'] . '</a>';
            if ($item['AllDay'] == 'false') {
                $html .= '<div class="time" slot="time">' . date("g:i A", strtotime($item['StartDateTime'])). ' ' . $item['Timezone'] . '</div>';
            } else {
                $html .= '<div class="time" slot="time">All Day</div>';
            }
            if ($item['LocationName'] != null) {
                $html .= '<div class="location" slot="location">' . $item['LocationName'] . '</div>';
            }

            // pricing and tickets
            if($item['TicketsExist'] == 'Yes') {

                if($item['IsFree'] == 'true') {
                    $html .= '<p slot="price">Free</p>';
                    if (!empty($item['TicketsUrl'])) {
                        $html .= '<a slot="tickets-link" target="_blank" href="' . $item['TicketsUrl'] . '">FREE TICKETS</a>';
                    }
                } else { // price or range
                    if (empty($item['HighPrice'])) {
                        $html .= '<p slot="price">Tickets: $' . $item['LowPrice'] . '</p>';
                    } else {
                        // will come back and get high price working
//            $html .= '<p slot="price">Tickets: $' . $item['LowPrice'lk] . '</p>';
                        $html .= '<p slot="price">Tickets: $' . $item['LowPrice'] . ' - $' . $item['HighPrice'] . '</p>';
                    }

                    if (!empty($item['TicketsUrl'])) {
                        $html .= '<a slot="tickets-link" target="_blank" href="' . $item['TicketsUrl'] . '">TICKETS</a>';
                    }
                }
            }
            $html .= '<a href="' . $item['FullUrl'] . '" slot="link" target="_blank">SEE FULL EVENT</a>';

            $html .= '</byu-calendar-row>';
            $count++;
        }
//    $html .= '</div>'; // ending the wrapping div with start and end date classes
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
.block-calendar-widget-block div {
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
.block-calendar-widget-block {
    width: 289px;
}

.block-calendar-widget-block h2 {
    color: #002e5d; // navy
    border-bottom: 1px solid #e5e5e5;
    font-size: 28px;
    padding-bottom: 6px;
}

.calendar-widget-block.display-list {
    /*width: 289px;*/
    width: 100%; // let width be defined by it's parent so it's controlled by the individual website.
    margin-right: 20px;
}
@media screen and (max-width: 1023px) {
    .block-calendar-widget-block {
        width: 100%;
    }
    .calendar-widget-block.display-list {
        width: 100%;
        margin-right: 0px;
    }
}
.block-calendar-widget-block .date-wrapper {
    display: flex;
    margin-bottom: 12px;
}
.block-calendar-widget-block .date-day-number {
    font-family: "Sentinel A", "Sentinel B";
    font-weight: bold;
    font-size: 26px;
    margin-right: 7px;
}
.block-calendar-widget-block .date-text {
    font-weight: 500;
    font-size: 21px;
    padding-top: 4px;

}


.block-calendar-widget-block .event-content {
    padding: 0px 0px 15px 15px;
    display: flex;
    justify-content: space-between;
    line-height: 1.3em;
    font-size: 17px;
}

.block-calendar-widget-block .event-time {
    min-width: 60px;
    margin-left: 12px;
    color: #767676;
    font-size: 16px;
    display: flex;
    justify-content: flex-end;
}


</style>
CSS;

echo $css;

?>
