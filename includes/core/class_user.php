<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function user_edit_info($user_id) {
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email
            FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'user_id' => (int) $row['user_id'],
                'plot_id' => (int) $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => (int) $row['phone'],
                'email' => $row['email']
            ];
        } else {
            return [
                'user_id' => 0,
                'plot_id' => '',
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => ''
            ];
        }
    }

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {
            $where[] = "phone LIKE '%".$search."%' OR first_name LIKE '%".$search."%' OR last_name LIKE '%".$search."%' OR email LIKE '%".$search."%'";
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone, last_login 
            FROM users ".$where." ORDER BY plot_id LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            if($row['last_login'] != 0 && !empty($row['last_login'])) $last_login = date('Y.m.d, H:i:s', $row['last_login']);
            else $last_login = '';
            $items[] = [
                'user_id' => $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'last_login' => $last_login
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_edit_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) && is_numeric($d['phone']) ? $d['phone'] : 0;
        $email = isset($d['email']) && trim($d['email']) ? trim(strtolower($d['email'])) : '';
        $plot_id = isset($d['plot_id']) && trim($d['plot_id']) ? trim($d['plot_id']) : '';
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // check validate
        $error_code = 0;
        $error_message = '';
        if(empty($d['email'])){
            $error_code = 4;
            $error_message = 'Email is required';
        }
        if(empty($d['phone'])){
            $error_code = 3;
            $error_message = 'Phone is required';
        }elseif(!is_numeric($d['phone'])){
            $error_code = 3;
            $error_message = 'Invalid phone';
        }
        if(empty($d['last_name'])){
            $error_code = 2;
            $error_message = 'Last Name is required';
        }
        if(empty($d['first_name'])){
            $error_code = 1;
            $error_message = 'First Name is required';
        }
        if($error_code !== 0) {
            $user_data = [
                'user_id' => $user_id,
                'first_name' => $d['first_name'],
                'last_name' => $d['last_name'],
                'phone' => $d['phone'],
                'email' => $d['email'],
                'error' => []
            ];
            $user_data['error'] = error_response($error_code, $error_message);
            HTML::assign('user', $user_data);
            return ['error' => true, 'html' => HTML::fetch('./partials/user_edit.html')];
        }else {
            // update & insert
            if ($user_id) {
                $set = [];
                $set[] = "plot_id='" . $plot_id . "'";
                $set[] = "first_name='" . $first_name . "'";
                $set[] = "last_name='" . $last_name . "'";
                $set[] = "phone='" . $phone . "'";
                $set[] = "email='" . $email . "'";
                $set[] = "updated='" . Session::$ts . "'";
                $set = implode(", ", $set);
                DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
            } else {
                DB::query("INSERT INTO users (
                plot_id,
                first_name,
                last_name,
                phone,
                email,
                updated
            ) VALUES (
                '" . $plot_id . "',
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $phone . "',
                '" . $email . "',
                '" . Session::$ts . "'
            );") or die (DB::error());
            }
            // output
            return User::users_fetch(['offset' => $offset]);
        }
    }

    public static function user_delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        if($user_id){
            DB::query("DELETE FROM users WHERE user_id='".$user_id."'") or die (DB::error());
        }
        return User::users_fetch(['offset' => $offset]);
    }

}
