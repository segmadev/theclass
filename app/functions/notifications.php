<?php
class Notifications extends content
{

    
    function get_pending_all_notification(string $userID, $start, $limit, $for = "")
    {
        $nofications = $this->getall("notifications", "(userID = ? or userID = ?) and status = ? and time_set <= ? and (expire_date >= ? or expire_date is null or expire_date = ?)  order by time_set desc LIMIT $start, $limit", ["all", $userID, "1", time(), time(), ""], fetch: "all");
        return $nofications;
        // var_dump($userID);
        // $more_where = "";
        // $more_value = "";
        // if ($for != "") {
        //     $more_where = "and n_for = ?";
        // }
        // $type = "sn.notificationID IS NULL and sn.userID"
        // LEFT JOIN sent_notifications as sn ON n.ID = sn.notificationID and n.userID = sn.userID
        // $nofications = $this->getall(
        //     "
        //     notifications as n",
        //     "n.userID = ? or n.userID = ? $more_where and n.status = ? and n.exclude NOT LIKE '%$userID%' order by n.date DESC LIMIT $start, $limit",
        //     array_values(array_filter([$userID, "all", $for, 'active'])),
        //     "n.*",
        //     fetch: "moredetails"
        // );
        // $nofications = $this->getall("
        // notifications", 
        // "userID = ? or userID = ? $more_where and status = ? and exclude NOT LIKE '%$userID%' and $type IS NULL order by date DESC", 
        // array_values(array_filter([$userID, "all", $for, 'active'])), 
        // "n.*",
        // fetch: "moredetails");
    }

    function unread_no($userID) {
        $note = $this->getall(" notifications n
    LEFT JOIN sent_notifications sn 
    ON n.ID = sn.notificationID 
    AND sn.userID = ?
        ", "
        (sn.seen_time IS NULL 
        OR sn.seen_time = '')
        and n.status = ? and (n.userID = ? or n.userID = ?) and n.time_set <= ? and (n.expire_date >= ? or n.expire_date is null or n.expire_date = ?)
   ", [$userID, 1, $userID, "all", time(), time(), ""], "COUNT(*) as count");
   return $note['count'];

    }

    function get_type_notifications($userID = "all", $date = null, $type = "global_report", $exclude = "", $update = false)
    {
        if ($date == null) {
            $date = date("Y-m-d");
        }
        if ($exclude != "") {
            $exclude = "and n.exclude NOT LIKE '%$exclude%'";
        }
        // if($userID = "all") {
        // }
        $notification =  $this->getall("notifications as n LEFT JOIN sent_notifications as sn ON n.ID = sn.notificationID and n.userID = sn.userID", "n.userID = ? and n.n_for = ? and n.date_set = ? and n.status = ? $exclude and sn.notificationID IS NULL and sn.userID IS NULL order by n.date DESC", [$userID, $type, $date, 'active'], "n.*");
        if (is_array($notification) && $update != false) {
            if ($userID == "all") {
                $this->exclude_user($notification['ID'], $update);
            } else {
                $this->sent_notification($notification['ID'], $update);
            }
        }
        return $notification;
    }

    function exclude_user($ID, $userID)
    {
        $data = $this->getall("notifications", "ID = ? and exclude NOT LIKE '%$userID%'", [$ID], "userID, status, exclude");
        if (!is_array($data)) {
            return false;
        }
        $info = [];
        if ($data['userID'] != "all") {
            $info['status'] = "deactive";
        }
        if ($data['exclude'] == "NULL") {
            $info['exclude'] = $userID;
        } else {
            $info['exclude'] = $data['exclude'] . ' , ' . $userID;
        }
        if ($this->update("notifications", $info, "ID = '$ID'")) {
            return true;
        }
    }
    function clear_notification($userID)
    {
        $data = $this->getall("notifications", "forID = ? and exclude NOT LIKE '%$userID%'", [$ID], "userID, status, exclude");
        if (!is_array($data)) {
            return false;
        }
        $info = [];
        if ($data['userID'] != "all") {
            $info['status'] = "deactive";
        }
        if ($data['exclude'] == "NULL") {
            $info['exclude'] = $userID;
        } else {
            $info['exclude'] = $data['exclude'] . ' , ' . $userID;
        }
        if ($this->update("notifications", $info, "forID = '$userID'")) {
            return true;
        }
    }



    function sent_notification($nID, $userID)
    {
        // var_dump($nID);
        if ($this->getall("sent_notifications", "userID = ? and notificationID = ?", [$userID, $nID], fetch: "") > 0) {
            return true;
        };
        if ($this->quick_insert("sent_notifications", ["userID" => $userID, "notificationID" => $nID, "sent_time" => time()])) {
            return true;
        }
        return false;
    }

    function seen_message($nID, $userID) {
        if(!$this->sent_notification($nID, $userID)) return false;
        if($this->is_seen($nID, $userID)) {
            return true;
        }
        if($this->update("sent_notifications", ["seen_time"=>time()], "notificationID = '$nID' and userID = '$userID'")) return true;
        return false;
    }

    function is_seen($nID, $userID) {
        if($this->getall("sent_notifications", "userID = ? and notificationID = ? and seen_time != ?", [$userID, $nID, ""], fetch: "") > 0) {
            return true;
        }
        return false;
    }
    function display_chat_notification($notifications)
    {
        if ($notifications->rowCount() == 0) {
            return false;
        }
        // body, title, message, icon, url
        $n = $notifications->rowCount();
        $title = $n . " Notificatiion(s) unread";
        $body = "You have $n unread  notification(s) on " . company_name . '. Click to read them.';
        $icon = ROOT . "assets/images/logos/" . $this->get_settings("favicon");
        $url =  ROOT . "index?p=notifications";
        $tag = "No_of_pending_notifications_" . uniqid();
        $return = ["function" => ["display_notification", "data" => ["title=$title&body=$body&icon=$icon&url=$url&tag=$tag", "null"]]];
        return json_encode($return);
    }

    function pop_message($message, $userID = null)
    {
        if (!is_array($message)) {
            return false;
        }
        $title = $message['title'];
        $body = $message['description'];
        $icon = $message['icon'];
        $url =  $message['url'];
        $tag = "new_message" . uniqid();
        $return = ["function" => ["display_notification", "data" => ["title=$title&body=$body&icon=$icon&url=$url&tag=$tag", "null"]]];
        if ($userID !=  null) {
            $this->send_email($userID, $title, $body);
        }
        return json_encode($return);
    }
    function show_notification_list(array $data, $userID)
    {
        if ($data['status'] !=  1) {
            return false;
        }
        $this->sent_notification($data['ID'], $userID);
        $readClass = "";
        if($this->is_seen($data['ID'], $userID)){
            $readClass = "text-muted";
        }
        $icon = '<div class="rounded-circle bg-light-primary text-center align-items-center d-flex justify-content-center" style="width: 48px; height: 48px;"><span class="ti ti-messages fs-5 text-primary '.$readClass.'"></span></div>';
        if ($data['icon'] != "" || $data['icon'] != null) {
            $icon = '<img src="' . $data['icon'] . '" alt="user" class="rounded-circle" width="48" height="48" />';
        }
        $modalButton = $this->modal_attributes("modal?p=notifications&action=view&ID=".$data['ID'], "Notification");
        return '<a href="#" '.$modalButton.' class="pt-3 d-flex align-items-center dropdown-item">
            <span class="me-3 text-muted">
                ' . $icon . '
            </span>
            <div class="w-75 d-inline-block v-middle">
                <h6 class="mb-1 fw-semibold fs-3 '.$readClass.'">' . $this->short_text($data['title'], 18, true) . '</h6>
                <span class="d-block fs-3 '.$readClass.'">' . $this->short_text(strip_tags(htmlspecialchars_decode($data['description'])), 30, true) . '</span>
                <span class="d-block fs-1 '.$readClass.'">' . $this->ago($data['time_set']) . '</span>
            </div>
        </a>';
    }

    // function get_all_pending_notifications($userID, $for = "") {
    //     $more_value = "";
    //     if($for != "") {
    //         $more_where = "and n_for = ?";
    //     }
    //     $nofications = $this->getall("
    //     notifications as n LEFT JOIN sent_notifications as sn ON n.ID = sn.notificationID and n.userID = sn.userID", 
    //     "n.userID = ? or n.userID = ? $more_where and n.exclude != ? and n.status = ? and sn.notificationID IS NULL and sn.seen_time IS NULL order by n.date DESC", 
    //     array_values(array_filter([$userID, "all", $for, $userID, 'active'])), 
    //     fetch: "moredetails");
    //    return $nofications;
    // }
}
