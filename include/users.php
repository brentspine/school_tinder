<?php

ob_start();
require $_SERVER['DOCUMENT_ROOT'] . "/include/functions.php";
ob_end_clean();

$uid = get_user_by_session($con);

function does_user_exist($name, $con) {
    $result = prepared_statement_result("SELECT * FROM users WHERE username = ? OR mail = ?", $con, true, "ss", $name, $name);
    if($result -> num_rows > 0)
        return true;

    return false;
}

function get_user_id($name, $con) {    
    $result = prepared_statement_result("SELECT * FROM users WHERE username = ? OR mail = ?", $con, true, $name, $name);
    if($result -> num_rows > 0)
        return get_database_entry_result($result, "id");

    return -1;
}

function is_session_available($uid, $con) {
    if(isset($_COOKIE["session"])) {
        $result = prepared_statement_result("SELECT * FROM sessions WHERE uid = ? AND token = ?", $con, true, "ss", $uid, $_COOKIE["session"]);
        if($result -> num_rows > 0)
            return true;
        setcookie("session", "");
    }
    return false;
}

function get_session() {
    return $_COOKIE["session"];
}

function get_user_by_session($con) {
    if(!isset($_COOKIE["session"])) return -1;
    $result = prepared_statement_result("SELECT * FROM sessions WHERE token = ?", $con, true, "s", $_COOKIE["session"]);
    return get_database_entry_result($result, "uid");
}

function get_user_by_username($username, $con) {
    $ps = $con -> prepare("SELECT * FROM users WHERE username = ?");
    $ps -> bind_param("s", $username);
    $ps -> execute();
    return get_row_result($ps -> get_result(), "id");
}

function get_user_permissions($uid, $con) {
    $sql = "SELECT permissions.bit, permissions.name FROM roles 
                LEFT JOIN permissions 
                ON roles.permissions & permissions.bit 
                WHERE roles.id = (SELECT role FROM users WHERE id = ?) 
            UNION 
            SELECT permissions.bit,permissions.name  
                FROM users LEFT JOIN permissions ON users.permissions & permissions.bit
                WHERE users.id = ?";
    $ps = $con -> prepare($sql);
    $ps -> bind_param("ss", $uid, $uid);
    $ps -> execute();
    return get_multiple_database_entries_result($ps -> get_result(), array("bit", "name"));
}

function get_user_permissions_bits($uid, $con) {
    $r = [];
    foreach(get_user_permissions($uid, $con) as $c)
        $r[] = $c[0];
    return $r;
} 

function get_user_permissions_names($uid, $con) {
    $r = [];
    foreach(get_user_permissions($uid, $con) as $c)
        $r[] = $c[1];
    return $r;
} 

function has_user_permission($uid, $name, $con) {
    $permissions = get_user_permissions_names($uid, $con);
    $args = explode(".", $name);
    $args2 = [];
    for($i = 0; $i < sizeof($args); $i++) {
        $r = $args[0];
        for($j = 1; $j <= $i; $j++)
            $r .= "." . $args[$j];
        $args2[] = $r . ".*";
    }
    foreach($permissions as $c)
        if(in_array($c, $args2)) return true;
    return in_array($name, $permissions) || in_array("op", $permissions);
}

function has_user_permission_bit($uid, $bit, $con) {
    $permissions = get_user_permissions_bits($uid, $con);
    return in_array($bit, $permissions) || in_array("1", $permissions);
}

?>