<?php
namespace Home\Controller;
use Think\Controller;
use PhpMyAdmin\SqlParser\Components\Condition;
use Think\vendor\autoloadphp;

class MartiAdminController extends Controller
{
    public function admin_login()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);

        $username = $data->username;
        $password = $data->password;

        $check = M("marti_admins")
            ->where(["username" => $username, "password" => md5($password)])
            ->select();

        if (count($check) > 0) {
            session("ID", $check[0]["id"]);

            response(true, "logged in", true);
        } else {
            response(false, "email or password is wrong", false);
        }
    }

    public function add_admin()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);

        if (session("ID") != null) {
            $username = $data->username;
            $email = $data->email;
            $password = $data->password;

            if (
                is_null($username) ||
                strlen($username) < 1 ||
                is_null($email) ||
                strlen($email) < 1 ||
                is_null($password) ||
                strlen($password) < 1
            ) {
                response(false, "do not leave empty", false);
            }

            if (
                strlen($password) < 7 ||
                preg_match("/[A-Z]/", $password) == false ||
                preg_match("#[0-9]#", $password) == false
            ) {
                response(
                    false,
                    "password cannot be smaller than 7 characters and must include at least one upper case letter and at least one special character",
                    false
                );
            }
            $check = M("marti_admins")
                ->where(["email" => $email])
                ->find();

            if (count($check) > 0) {
                response(false, "email exists", false);
            }
            $user = M("marti_admins");
            $data_list[] = [
                "username" => $username,
                "email" => $email,
                "password" => md5($password),
            ];
            $user->addAll($data_list);
            response(true, "registration completed", true);
        }
    }
    public function list_marti()
    {
        $check = M("marti")->select();
        response(true, $check, true);


    }

    public function add_marti()
    {

        $data = get_json_data();

        $battery = $data->battery;
        $price = $data->fixed_cost;
        $latitude = $data->latitude;
        $longitude = $data->longitude;
        $rate = $data->rate_per_minute;

        $datalist[] = [
            "battery" => $battery,
            "fixed_cost" => $price,
            "latitude" => $latitude,
            "longitude" => $longitude,
            "rate_per_minute" => $rate
        ];

        $marti = M('marti');
        $marti->addAll($datalist);
        response(true, 'marti added', true);

    }

    public function delete_marti()
    {
        $json = file_get_contents("php://input");
        $data = json_decode($json);
        $id = $data->id;

        $check = M("marti");
        $check->where(["id" => $id])->delete();
        response(true, "marti deleted", true);
    }
    public function admin_logout()
    {
        session("ID", null);
        response(true, "logged out", true);
    }


    public function generate_marti()
    {   $data=get_json_data();
        $number=$data->number;

        $marti = M('marti');

        $martis = array();
        for ($i = 0; $i < $number; $i++) {
            $min_lat = 41.069535;
            $max_lat = 41.106861;
            $min_long = 28.979303;
            $max_long = 29.029070;
            $decimals = 6;

            $divisor = pow(10, $decimals);
            $randomLat = mt_rand($min_lat * $divisor, $max_lat * $divisor) / $divisor;
            $randomLong = mt_rand($min_long * $divisor, $max_long * $divisor) / $divisor;
            $Lat_str = sprintf("%.6f", $randomLat);
            $Long_str = sprintf("%.6f", $randomLong);

            $percent = rand(30, 100);
            $battery = '%' . $percent;

            $fixed_price_array = [2.99, 3.99];
            $random_fee = $fixed_price_array[rand(0, 1)];

            array_push($martis, [
                "battery" => $battery,
                "fixed_cost" => $random_fee,
                "latitude" => $Lat_str,
                "longitude" => $Long_str,
                "rate_per_minute" => $random_fee
            ]);

        }
        $marti->addAll($martis);


        response(true, count($martis) . " number of Martis generated.", true);
    }

    public function active()
    {   $data=get_json_data();
        $number=$data->number;

        for ($i = 0; $i < $number; $i++) {
            $marti = M('marti');
            $martilar = $marti->select();
            $first_marti=$martilar[0]['marti_id'];
            $last_marti=$martilar[count($martilar)-1]['marti_id'];
            
            
            $id = rand($first_marti, $last_marti);
            $marti->where(["marti_id" => $id])->save(["active" => 1]);
        }
        response(true, "30 Martis activated", true);
    }

    public function move_marti()
    {
        $with_dest = M('marti_destinations');
        $dest_martis = $with_dest->select();

        foreach ($dest_martis as $marti) {
            $marti_id = $marti['marti_id'];
            $dest_lat = $marti['dest_lat'];
            $dest_long = $marti['dest_long'];
            $martilar = M('marti');
            $marticik =$martilar->where(["marti_id" => $marti_id])->find();
            $marti_lat = $marticik['latitude'];
            $marti_long = $marticik['longitude'];
            $marti_lat2= sprintf("%.5f", $marti_lat);
            $marti_long2= sprintf("%.5f", $marti_long);

            if($marti_lat2<$dest_lat){
                $martilar->where(["marti_id" => $marti_id])->setField(["latitude" => $marti_lat+0.00001]);
            }
            elseif($marti_lat2>$dest_lat){
                $martilar->where(["marti_id" => $marti_id])->setField(["latitude" => $marti_lat-0.00001]);
            }
            
            if($marti_long2<$dest_long){
                $martilar->where(["marti_id" => $marti_id])->setField(["longitude" => $marti_long+0.00001]);
            }
            elseif($marti_long2>$dest_long){
                $martilar->where(["marti_id" => $marti_id])->setField(["longitude" => $marti_long-0.00001]);
            }

        } 
        response(true, "Martis are on the way to their destinations", true);
                

    }



    public function move_randomly(){

        $move_lat=array(0.00003,0.00004,0.00005,-0.00003,-0.00004,-0.00005);


        $table = M('marti');

        $check = $table->where(['active' => 1])->limit(100)->select();

        foreach ($check as $value){
            if(count($check)==0){
                continue;
            }
           
            $lat1 = $value['latitude'];
            $lon1 = $value['longitude'];
            
            $lat2= $lat1+ shuffle($move);
            $move_long=(shuffle($move_lat)/cos($lat2));
            $lon2= $lon1+ $move_long;
            $Lat_str = sprintf("%.6f", $lat2);
            $Long_str = sprintf("%.6f", $lon2);
            $table->where(['active'=>1,"marti_id"=>$value["marti_id"]])->save(['latitude' => $Lat_str, 'longitude' => $Long_str]);

                
        }
        response(true, "randomly moving martis", true);
       

    }

    // public function call(){
    //     $a = 41.123456;
    //     $b = 21.123456;
    //     $Lat_str = sprintf("%.5f", $a);
    //     $Long_str = sprintf("%.5f", $b);
    //     echo $Lat_str;
    //     echo"<br>";
    //     echo $Long_str;
    // }

    public function add_destination(){


        $marti = M('marti');
        $active_martis = $marti->where(['active' => 1])->select();
        for ($i=0; $i < rand(5, 15);$i++){
            $marti_id = $active_martis[rand(0, count($active_martis)-1)]['marti_id'];
            $min_lat = 41.069535;
            $max_lat = 41.106861;
            $min_long = 28.979303;
            $max_long = 29.029070;
            $decimals = 6;

            $divisor = pow(10, $decimals);
            $randomLat = mt_rand($min_lat * $divisor, $max_lat * $divisor) / $divisor;
            $randomLong = mt_rand($min_long * $divisor, $max_long * $divisor) / $divisor;
            $Lat_str = sprintf("%.5f", $randomLat);
            $Long_str = sprintf("%.5f", $randomLong);

            $with_dest = M('marti_destinations');
            $with_dest->add(['marti_id'=>$marti_id,'dest_lat'=>$Lat_str,'dest_long'=>$Long_str]);


        }
        response(true, "Destinations added", true);
       

    }

   
}