<?php
class ModelExtensionModuleApimodule extends Model
{
    private $API_VERSION = 2.0;

    public function getVersion()
    {
        return $this->API_VERSION;
    }

    // public function getAllDevices()
    // {
    //     $query = $this->db->query(
    //         "SELECT udma.*, u.username "
    //        ."FROM " . DB_PREFIX . "user_device_mob_api AS udma "
    //        ."LEFT JOIN " . DB_PREFIX . "user AS u ON udma.user_id=u.user_id"
    //     );

    //     if(count($query->rows) > 0) {
    //         return $query->rows;
    //     }else{
    //         return null;
    //     }
    // }

    // public function deleteUserDeviceByToken($device_token)
    // {
    //     $sql = "DELETE FROM `" . DB_PREFIX . "user_device_mob_api` WHERE  device_token = '" . $device_token . "'";
    //     $this->db->query($sql);
    //     return;
    // }

    public function install()
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "user_token_mob_api` ("
               ."`id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT, "
               ."`user_id` INT NOT NULL, "
               ."`token` VARCHAR(32) NOT NULL"
           .")"
        );
        // $this->db->query(
        //     "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "user_device_mob_api` ("
        //        ."`id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT, "
        //        ."`user_id` INT NOT NULL, "
        //        ."`device_token` VARCHAR(500), "
        //        ."`os_type` VARCHAR(20), "
        //        ."`device_name` VARCHAR(255) DEFAULT NULL, "
        //        ."`last_login` DATETIME DEFAULT NULL, "
        //        ."`created_at` DATETIME DEFAULT NULL"
        //    .")"
        // );
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX."user_token_mob_api`");
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX."user_device_mob_api`");
    }
}
