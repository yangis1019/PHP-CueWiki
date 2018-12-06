<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE);

    $lang_file = [];
    
    $lang_file["en-US"] = [];
    
    $lang_file["en-US"]["edit"] = "Edit";
    $lang_file["en-US"]["main"] = "Main";
    $lang_file["en-US"]["recent_changes"] = "Recent changes";
    $lang_file["en-US"]["history"] = "History";
    $lang_file["en-US"]["return"] = "Return";
    $lang_file["en-US"]["version"] = "Version";

    $inter_version = "v0.0.01";
    $version = "0001";

    function load_render($title, $data) {
        $data = preg_replace("/'''((?:(?!''').)+)'''/", "<b>$1</b>", $data);
        $data = preg_replace("/''((?:(?!'').)+)''/", "<i>$1</i>", $data);

        return $data;
    }
    
    function redirect($url = '') {
        return '<meta http-equiv="refresh" content="0; url='.$_SERVER['PHP_SELF'].$url.'">';
    }

    function file_fix($url) {
        return preg_replace('/\/index.php$/' , '', $_SERVER['PHP_SELF']).$url;
    }

    function url_fix($url = '') {
        return $_SERVER['PHP_SELF'].$url;
    }
    
    function load_lang($data) {
        global $lang_file;

        if($lang_file["en-US"][$data]) {
            return $lang_file["en-US"][$data];
        } else {
            return $data.' (Missing)';
        }
    }
    
    function load_skin($head = '', $body = '', $tool = [], $other = []) {
        $tool_html = "";
        foreach($tool as &$tool_data) {
            $tool_html = $tool_html."<a href=".url_fix($tool_data[1]).">".$tool_data[0]."</a> ";
        }

        if(!$other["title"]) {
            $other["title"] = "";
        }

        if(!$other["sub"]) {
            $other["sub"] = "";
        }

        $title = "";
        if($other["title"] !== "") {
            $title = $other["title"];

            if($other["sub"] !== "") {
                $title = $title." (".$other["sub"].")";
            }
        }
        
        $main_skin = "
            <html>
                <head>
                    ".$head."
                </head>
                <body>
                    <div id=\"top\">
                        <a href=\"".url_fix()."\">".load_lang("main")."</a> <a href=\"?action=r_change\">".load_lang("recent_changes")."</a>
                    </div>
                    <br>
                    <br>
                    <div id=\"middle\">
                        <div id=\"title\">
                            ".$title."
                        </div>
                        <br>
                        <br>
                        <div id=\"tool\">
                            ".$tool_html."
                        </div>
                        <br>
                        <br>
                        <div id=\"data\">
                            ".$body."
                        </div>
                    </div>
                    <br>
                    <br>
                    <div id=\"bottom\">
                        openNAMU_Lite
                    </div>
                </body>
            </html>
        ";

        return $main_skin;
    }
    
    $conn = new PDO('sqlite:data.db');
    session_start();

    $create = $conn -> prepare('create table if not exists setting(title text, data text)');
    $create -> execute();
    
    $select = $conn -> prepare('select data from setting where title = "version"');
    $select -> execute();
    $data = $select -> fetchAll();
    if(!$data) {
        $create = $conn -> prepare('create table if not exists history(num text, title text, data text, date text, who text)');
        $create -> execute();

        $create = $conn -> prepare('alter table history add why text default ""');
        $create -> execute();

        $create = $conn -> prepare('alter table history add blind text default ""');
        $create -> execute();

        $insert = $conn -> prepare('insert into setting (title, data) values ("version", ?)');
        $insert -> execute(array($version));
    }

    switch($_GET['action']) {
        case "":
            echo redirect("?action=w&title=Wiki:Main");

            break;
        case "w":
            if($_GET['title']) {
                $select = $conn -> prepare('select data from history where title = ? order by date desc limit 1');
                $select -> execute(array($_GET['title']));
                $data = $select -> fetchAll();
                if($data) {
                    $get_data = load_render($_GET['title'], $data[0]["data"]);
                } else {
                    $get_data = "404";
                }
                
                echo load_skin("", $get_data,
                    [
                        [load_lang('edit'), '?action=edit&title='.$_GET['title']],
                        [load_lang("history"), '?action=history&title='.$_GET['title']]
                    ], ["title" => $_GET['title']]);
            } else {
                echo redirect();
            }

            break;
        case "edit":
            if($_GET['title']) {
                if($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $select = $conn -> prepare('select num from history where title = ? order by date desc limit 1');
                    $select -> execute(array($_GET['title']));
                    $data = $select -> fetchAll();
                    if($data) {
                        $num = (string)((int)$data[0]["num"] + 1);
                    } else {
                        $num = "1";
                    }
                    
                    if(mb_strlen($_POST["why"], 'UTF-8') > 64) {
                        $why = "";
                    } else {
                        $why = $_POST["why"];
                    }

                    $insert = $conn -> prepare('insert into history (num, title, data, date, who, why, blind) values (?, ?, ?, ?, ?, ?, "")');
                    $insert -> execute(array($num, $_GET['title'], $_POST["data"], (string)date("Y-m-d H:i:s"), $_SERVER['REMOTE_ADDR'], $why));

                    echo redirect("?action=w&title=".$_GET['title']);
                } else {
                    $select = $conn -> prepare('select data from history where title = ? order by date desc limit 1');
                    $select -> execute(array($_GET['title']));
                    $data = $select -> fetchAll();
                    if($data) {
                        $get_data = $data[0]["data"];
                    } else {
                        $get_data = "";
                    }

                    echo load_skin("", "
                        <form method=\"post\">
                            <textarea style=\"width: 500px; height: 300px;\" name=\"data\">".$get_data."</textarea>
                            <br>
                            <br>
                            <input name=\"why\"></input>
                            <br>
                            <br>
                            <button type=\"submit\">".load_lang("save")."</button>
                        </form>
                    ", [[load_lang("return"), "?action=w&title=".$_GET['title']]], ["title" => $_GET['title'], "sub" => load_lang("edit")]);
                }
            } else {
                echo redirect();
            }

            break;
        case "history":
            if($_GET['title']) {
                $html_data = '';

                $select = $conn -> prepare('select num, title, date, who, why from history where title = ? order by date desc');
                $select -> execute(array($_GET['title']));
                $data = $select -> fetchAll();
                foreach($data as &$in_data) {
                    $html_data = $html_data."
                        ".$in_data["num"]." | ".$in_data["date"]." | ".$in_data["who"]." | ".$in_data["why"]."
                        <br>
                    ";
                }

                echo load_skin("", $html_data, [[load_lang("return"), "?action=w&title=".$_GET['title']]], ["title" => $_GET['title'], "sub" => load_lang("history")]);
            } else {
                echo redirect();
            }

            break;
        case "r_change":
            $html_data = '';

            $select = $conn -> prepare('select num, title, date, who from history order by date desc');
            $select -> execute();
            $data = $select -> fetchAll();
            foreach($data as &$in_data) {
                $html_data = $html_data."
                    ".$in_data["num"]." | ".$in_data["date"]." | ".$in_data["who"]."
                    <br>
                ";
            }

            echo load_skin("", $html_data, [], ["title" => load_lang("recent_changes")]);

            break;
        default:
            echo redirect();

            break;
    }
?>