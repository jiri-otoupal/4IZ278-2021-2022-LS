<?php

require_once "DB.php";
require_once realpath(dirname(__FILE__) . '/..') . "/../utils/utils.php";
require_once realpath(dirname(__FILE__) . '/..') . "/../consensus/config.php";

class Race extends DB
{
    protected $id;

    public function __construct($id = null)
    {
        parent::__construct();
        if ($id == null)
            return;
        $this->$id = $this->escape($id);
    }

    public function getAll()
    {
        $results = $this->query("SELECT race.*
             , GROUP_CONCAT(w.step, ',', w.latitude, ',', w.longitude ORDER BY w.step SEPARATOR ';') as waypoints_np
        
        FROM race
                 LEFT JOIN waypoint w on race.race_id = w.race_id
                 LEFT JOIN user_race_fk urf on race.race_id = urf.race_id
        GROUP BY race.race_id;");

        // Decode JSON from database waypoint selection concat
        //for ($i = 0; $i < sizeof($results); $i++)
        //    $results[$i]["waypoints"] = json_decode($results[$i]["waypoints"]);

        return $results;
    }

    public function getWaypoints($race_id)
    {
        $escaped_race_id = $this->escape($race_id);
        return $this->query("SELECT waypoint.step,waypoint.latitude,waypoint.longitude
            FROM waypoint JOIN race r on waypoint.race_id = r.race_id 
            WHERE r.race_id='$escaped_race_id'");

    }

    public function getJoined($user_id)
    {
        $escaped_id = $this->escape($user_id);

        return $this->query("SELECT * FROM race JOIN
            user_race_fk urf on race.race_id = urf.race_id WHERE user_id='$escaped_id' ORDER BY start_time ASC");
    }

    public function join($user_id, $car_id, $race_id)
    {
        $escaped_id = $this->escape($user_id);
        $escaped_car_id = $this->escape($car_id);
        $escaped_race_id = $this->escape($race_id);

        return $this->non_return_query("INSERT INTO user_race_fk (race_id, user_id, car_id) 
            VALUES ('$escaped_race_id','$escaped_id','$escaped_car_id')");

    }

    public function leave($user_id, $race_id)
    {
        $escaped_id = $this->escape($user_id);
        $escaped_race_id = $this->escape($race_id);
        return $this->non_return_query("DELETE FROM user_race_fk WHERE user_id='$escaped_id' AND race_id='$escaped_race_id'");
    }

    /**
     * Increment step, if race did not start this will start it
     * @param $user_id
     * @param $race_id
     * @return bool|mysqli_result
     */
    public function nextWaypoint($user_id, $race_id)
    {
        $escaped_id = $this->escape($user_id);
        $escaped_race_id = $this->escape($race_id);
        return $this->non_return_query("UPDATE user_race_fk SET step=IFNULL(step, 0) + 1 WHERE user_id='$escaped_id' AND race_id='$escaped_race_id'");
    }

    public function getRace($id)
    {
        if ($id == null)
            return null;

        $escaped_id = $this->escape($id);
        $res = $this->query
        ("SELECT * FROM race WHERE race_id='$escaped_id'");

        if (!empty($res))
            return $res;
        return null;
    }

    public function clearWaypoints($raceId)
    {
        $escaped_raceId = $this->escape($raceId);
        //TODO: Secure owner_id

        return $this->non_return_query("DELETE FROM waypoint WHERE race_id='$raceId'");
    }

    public function addWaypoint($raceId, $step, $lat, $lng)
    {
        //TODO: Secure owner_id
        $escaped_raceId = $this->escape($raceId);
        $escaped_step = $this->escape($step);
        $escaped_lat = $this->escape($lat);
        $escaped_lng = $this->escape($lng);

        return $this->non_return_query("INSERT INTO waypoint 
                                                 (race_id, step, latitude, longitude) VALUES 
                                                                 ('$escaped_raceId','$escaped_step','$escaped_lat','$escaped_lng')");
    }

    public function bindForEdit($query_string, $type_string, $rid, $name, $start_time, $lat, $lng, $owner_id, $min_r, $max_r, $max_hp, $password, $heat_grade, $min_karma, $chat_link, $img_url = null, $laps = 1, $where_id = null)
    {
        $escaped_rid = $this->escape($rid);
        $escaped_name = $this->escape($name);
        $escaped_start_time = $this->escape($start_time);
        $escaped_lat = $this->escape($lat);
        $escaped_lng = $this->escape($lng);
        $escaped_owner_id = $this->escape($owner_id);
        $escaped_min_r = $this->escape($min_r);
        $escaped_max_r = $this->escape($max_r);
        $escaped_max_hp = $this->escape($max_hp);
        $escaped_password = $this->escape($password);
        $escaped_heat_grade = $this->escape($heat_grade);
        $escaped_min_karma = $this->escape($min_karma);
        $escaped_chat_link = $this->escape($chat_link);
        $escaped_laps = $this->escape($laps);
        $escaped_img_url = $this->escape($img_url);

        $prepared = $this->connection->prepare($query_string);

        $params = [$escaped_rid, $escaped_name, $escaped_start_time, $escaped_lat, $escaped_lng, $escaped_owner_id,
            $escaped_min_r, $escaped_max_r, $escaped_max_hp, $escaped_password, $escaped_heat_grade,
            $escaped_min_karma, $escaped_chat_link, $escaped_laps, $escaped_img_url];

        if (is_null($rid))
            array_shift($params);


        if (!is_null($where_id))
            $params[] = $where_id;

        $prepared->bind_param($type_string, ...$params);
        return $prepared->execute();
    }

    public function add($name, $start_time, $lat, $lng, $owner_id, $min_r, $max_r, $max_hp, $password, $heat_grade, $min_karma, $chat_link, $laps = 1, $img_url = null)
    {
        $query_string = "INSERT INTO race (name, start_time, latitude, longitude, owner_id, min_racers, max_racers, max_hp, password, heat_grade, min_req_karma, chat_link, laps, img_url)
        VALUES 
               (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $types = "ssddiiiissisis";
        return $this->bindForEdit($query_string, $types, null, $name, $start_time, $lat, $lng, $owner_id, $min_r, $max_r, $max_hp, $password, $heat_grade, $min_karma, $chat_link, $laps, $img_url);
    }

    public function modifyRace($race_id, $name, $start_time, $lat, $lng, $owner_id, $min_r, $max_r, $max_hp, $password, $heat_grade, $min_karma, $chat_link, $img_url = null, $laps = 1)
    {
        $query_string = "UPDATE race SET  
                race_id=?,name=?,start_time=?,
                latitude=?,longitude=?,owner_id=?,min_racers=?,
                             max_racers=?,max_hp=?,password=?,
                             heat_grade=?,min_req_karma=?,
                             chat_link=?,img_url=?,laps=?
             WHERE race_id=?";
        $types = "issddiiiississii";

        return $this->bindForEdit($query_string, $types, $race_id, $name, $start_time, $lat, $lng, $owner_id, $min_r,
            $max_r, $max_hp, $password, $heat_grade, $min_karma, $chat_link, $laps, $img_url, $race_id);
    }

    public function deleteRace($id, $user_id)
    {
        $escaped_user_id = $this->escape($user_id);
        $escaped_id = $this->escape($id);

        return $this->non_return_query("DELETE FROM race WHERE race_id='$escaped_id' AND owner_id='$escaped_user_id'");
    }
}