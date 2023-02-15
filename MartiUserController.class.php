<?php

namespace Home\Controller;
use Think\Controller;
use PhpMyAdmin\SqlParser\Components\Condition;
use Think\vendor\autoloadphp;

class MartiUserController extends Controller
{
    public function register()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);

        $name = $data->name;
        $surname = $data->surname;
        $email = $data->email;
        $password = $data->password;

        if (
            is_null($name) ||
            strlen($name) < 1 ||
            is_null($surname) ||
            strlen($surname) < 1 ||
            is_null($email) ||
            strlen($email) < 1
        ) {
            response(false, "Do not leave empty ", false);
        }

        if (is_null($password)) {
            response(false, "Please enter password", false);
        }
        if (strlen($password) < 7) {
            response(
                false,
                "Password can not be smaller than 7 characters",
                false
            );
        }
        if (
            preg_match("/[A-Z]/", $password) == false ||
            preg_match("#[0-9]#", $password) == false
        ) {
            response(
                false,
                "Password must include at least one uppercase letter and at least one number",
                false
            );
        }

        $check = M("marti_users")
            ->where(["email" => $email])
            ->find();

        if (count($check) > 0) {
            response(false, "email already exists", false);
        }

        $user = M("marti_users");

        $datalist[] = [
            "name" => $name,
            "surname" => $surname,
            "email" => $email,
            "password" => md5($password),
        ];

        $user->addAll($datalist);
        response(true, "Registration Completed!", true);
    }

    public function login()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);

        $email = $data->email;
        $password = $data->password;

        $check = M("marti_users")
            ->where(["email" => $email, "password" => md5($password)])
            ->select();

        if (count($check) > 0) {
            session("ID", $check[0]["id"]);
            response(true, "logged in", true);
        } else {
            response(false, "email or password is wrong", false);
        }
    }

    public function find_marti()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);
        $my_lat = $data->my_lat;
        $my_long = $data->my_long;

        $sql = "SELECT * ,(3959 * acos(cos(radians($my_lat))
         * cos(radians(latitude)) * cos(radians(longitude) - radians($my_long)) + sin(radians($my_lat)) 
         * sin(radians(latitude)))) AS distance FROM first_marti where `active`=0 HAVING distance < 2.000 ORDER BY distance";

        $result = M("marti")->query($sql);

        if (count($result) == 0) {
            response(false, "No Marti found within 2 kilometres", false);
        }
       
            response(true, $result, true);

        
    }

    public function list_marti()
    {
        $check = M("marti")->select();
        response(true, $check, true);
    }

    public function locate_marti()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);
        $deal_lat = $data->deal_lat;
        $deal_long = $data->deal_long;
        $geocode = file_get_contents(
            "http://maps.googleapis.com/maps/api/geocode/json?latlng=" .
                $deal_lat .
                "," .
                $deal_long .
                "&sensor=false"
        );

        $output = json_decode($geocode);
        // enter code here
        for (
            $j = 0;
            $j < count($output->results[0]->address_components);
            $j++
        ) {
            $cn = [$output->results[0]->address_components[$j]->types[0]];
            if (in_array("locality", $cn)) {
                $city = $output->results[0]->address_components[$j]->long_name;
            }
        }
        echo $city;
    }
    public function start_rental()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);
        $marti_id = $data->marti_id;
        $is_there= M('marti')->where(['marti_id'=>$marti_id])->select();
        if(count($is_there)<=0){
            response(false, "This Marti does not exist", false);
        }
        $is_active= M('marti')->where(['marti_id'=>$marti_id])->find();
        if($is_active['active']==1){
            response(false, "This Marti is already rented", false);
        }
        $sql = "SELECT `latitude`, `longitude` FROM `first_marti` WHERE `marti_id`=$marti_id";
        $latlong = M("marti")->query($sql);
        $first_lat = $latlong[0]["latitude"];
        $first_long = $latlong[0]["longitude"];
        date_default_timezone_set('Europe/Istanbul');
        $initial = time();
        $initial_date = date("m/d/Y H:i:s", $initial);
        $user = M("activity");
        $user_id = session("ID");
        $activate = M('marti');
        $activate->where(['marti_id'=>$marti_id])->save(['active'=>1]);
       

        $data_list[] = [
            "initial_time" => $initial,
            "marti_id" => $marti_id,
            "user_id" => $user_id,
            "first_lat" => $first_lat,
            "first_long" => $first_long,
            "initial_date" => $initial_date,
        ];
        $user->addAll($data_list);
        $check = M("activity")
            ->where([
                "user_id" => $user_id,
                "marti_id" => $marti_id,
                "initial_time" => $initial,
            ])
            ->select();
        session("session_ID", $check[0]["id"]);

        response(true, "rental started", true);
    }

    public function end_rental()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);
        $marti_id = $data->marti_id;
        $last_lat = $data->last_lat;
        $session_id = $data->session_id;
        $last_long = $data->last_long;
        date_default_timezone_set('Europe/Istanbul');
        $final = time();
        $final_date = date("m/d/Y H:i:s", $final);
        $sql = "SELECT `rate_per_minute` FROM `first_marti` WHERE `marti_id`=$marti_id";
        $rate = M("marti")->query($sql);
        $sql2 = "SELECT `fixed_cost` FROM `first_marti` WHERE `marti_id`=$marti_id";
        $fixed_cost = M("marti")->query($sql2);
        $sql1 = "SELECT `initial_time` FROM `first_activity` WHERE `id`=$session_id";
        $initial_time = M("activity")->query($sql1);
        $initial_time = $initial_time[0]["initial_time"];
        $rate = (float) $rate[0]["rate_per_minute"];


        $time_spent = ($final - $initial_time) / 60;
        $fee = ($time_spent * $rate) + $fixed_cost[0]["fixed_cost"];

        $user = M("activity");
        $user
            ->where(["id" => $session_id])
            ->setField([
                "last_lat" => $last_lat,
                "last_long" => $last_long,
                "final_date" => $final_date,
                "final_time" => $final,
                "fee" => $fee,
            ]);

        $check = M("marti");
        $check
            ->where(["marti_id" => $marti_id])
            ->setField(["latitude" => $last_lat, "longitude" => $last_long, "active"=>0]);
        response(true, " rental ended", true);
    }
    public function see_my_rides()
    {
        $see = M("activity")
            ->where(["user_id" => session("ID")])
            ->select();
        response(true, $see, true);
    }

    public function logout()
    {
        session("ID", null);
        response(true, "logged out", true);
    }


    
}
