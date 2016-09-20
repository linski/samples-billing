<?php

namespace RWS\UserAssistance\Controller;

use \RWS\Core;


/**
*
 * @author maxlinski
 */
class BillingReports extends Core\Controller {

    const CLASSES_REPO_PATH  = '\Api\Account\Billing\BillingReports';
    const CSV_DIR            = 'billing_reports';

    public function __construct()
    {
        parent::__construct();

        $logged_username        = $this->sentry->get_current_user()->getUsername();
        $settings               = \RWS\Core\SystemComponent::get_settings();
        $arr_accounts_usernames = $settings['ua_billing_reports_users'];

        if (!in_array($logged_username, $arr_accounts_usernames)) {

            $err = new Core\Error(E_ERROR, 'Restricted area, access denied');
            $err->handle();
        }
    }

    public function index( Core\Request $_req ) {
        if ( $_req->is_json() ) {
            try {
                $r = new Core\Response( 200, "Billing Reports Template data has been fetched successfully." );
                $r->add_data( 'reports', self::getListOfReports() );
                echo $r;
                die();                
            } catch ( Core\Error $e ) {
                $r = new Core\Response( 500, "Error - " . $e->getMessage() );
                echo $r;
                die();
            }
        } else {
            require($this->module_path . '/View/billing_reports.php');
        }
    }

    public function create( Core\Request $_req ) {
        if ( $_req->is_json() ) {

            $_post = $_req->extract_post();
            if ( !empty( $_post ) ) {
                try {
                    $start_date  = $_req->get_post( 'start_date' );
                    $start_date  = $start_date ? date('Y-m', strtotime($start_date)) : "";
                    $class_name  = $_req->get_post( 'list_of_reports' );
                    $pencode     = $_req->get_post( 'pencode_filter' ) ? : "";
                    $params      = [$start_date, $pencode];

                    $arr_data    = call_user_func(self::CLASSES_REPO_PATH . "\\" . $class_name . "::get_data", $params);
                    $arr_headers = call_user_func(self::CLASSES_REPO_PATH . "\\" . $class_name . "::get_headers");

                    $csv_body    = self::getCsvBody($arr_headers, $arr_data);

                    $user_id     = $this->sentry->get_current_user()->getId();
                    $csv_dir     = self::getCsvDir();
                    $csv_name    = self::getCsvFileName($class_name, $user_id);
                    $csv_path    =  $csv_dir . '/' . $csv_name;

                    if ($class_name!= "CMS6AllAccounts" && $class_name!="GW2AllAccounts") {

                        file_put_contents($csv_path, $csv_body);

                        $settings    = \RWS\Core\SystemComponent::get_settings();
                        $email_from  = $settings['email_address_notification'];
                        $name_from   = $settings['email_address_notification_name'];
                        $email_to    = $this->sentry->get_current_user()->getUsername();
                        $name_to     = trim($this->sentry->get_current_user()->getFirstName() . " " . $this->sentry->get_current_user()->getLastName());
                        $reportTitle = self::getReportTitle($class_name);
                        $subject     = $reportTitle .
                                       " (generated on " . date( "Y" ) . "-" . date( "m" ) . "-" . date( "d" ) .
                                       ", at " . date( "H" ) . ":" . date( "i" ) . ":" . date( "s" ) . ")";

                        $workload = [
                            'email_to'      => $email_to,
                            'name_to'       => $name_to,
                            'email_from'    => $email_from,
                            'name_from'     => $name_from,
                            'subject'       => $subject,
                            'body'          => $subject,
                            'text'          => $subject,
                            'file_name'     => $csv_name,
                            'file_path'     => $csv_path,
                        ];

                        $email_sent = self::sendReport($workload);
                    }

                    $r = new Core\Response(200, "Billing Report has been created successfully.");
                    
                    echo $r;
                    die();
                } catch (Core\Error $e) {
                    $r = new Core\Response(500, "Error - " . $e->getMessage());
                    echo $r;
                    die();
                }
            }
        } else {
            require($this->module_path . '/View/billing_reports.php');
        }
    }

    private static function getCsvFileName($class_name, $user_id) {

        return $user_id . '_' . $class_name . '_' .
               date( "Y" ) . date( "m" ) . date( "d" ) . '_' . date( "H" ) . date( "i" ) . date( "s" ) . '.csv';
    }

    private static function getCsvBody($arr_headers, $arr_data) {

        return self::getRowStringForCsv($arr_headers) . self::getDataString($arr_data);
    }

    private static function getRowStringForCsv($arr) {
        
        return '"' . implode('","',$arr) . '"' . "\r\n";
    }

    private static function getDataString($arr) {
        
        $result = "";
        foreach ($arr as $row) 
            $result .= self::getRowStringForCsv(array_values($row));

        return $result;
    }

    private static function getCsvDir() {
        
        $settings = \RWS\Core\SystemComponent::get_settings();
        $basepath = $settings['filesystem_basepath'];
        $top_dir  = self::getDir($basepath . "/" . self::CSV_DIR);
        $year_dir = self::getDir($top_dir  . "/" . date('Y'));

        return $year_dir;
    }
    
    private static function getDir($str_Dir) {
        
        if (!is_dir($str_Dir))
            mkdir ($str_Dir);
        
        return $str_Dir;
    }

    private static function getListOfReports() {
        
        return [
            ["class" => "GW2NewBrokerageAccounts", "title" => "gW2 - New Brokerage Accounts"],
            ["class" => "GW2AllAccounts",          "title" => "gW2 - All Accounts"],
            ["class" => "GW2ASKAccounts",          "title" => "gW2 - ASK Accounts"],
            ["class" => "CMS6AllAccounts",         "title" => "CMS6 - All Accounts"],
            ["class" => "AllReceivables",          "title" => "2015+ Receivables"],
            ["class" => "ThisMonthSales",          "title" => "This Month Sales"],
            ["class" => "LastMonthSales",          "title" => "Last Month Sales"],
            ["class" => "AllGW2andCMS6Payments",   "title" => "2015-07-01+ gW2 + CMS6 Payments"],
            ["class" => "GW2CMS6SalesItems",       "title" => "gW2 + CMS6 Sales Items"],
        ];
    }

    private static function getReportTitle($class_name) {
        
        $arr_reports = self::getListOfReports();
        foreach ($arr_reports as $arr) {
            if ($arr['class'] == $class_name)
                return $arr['title'];
        }

        return "";
    }
    
    private static function sendReport($workload) {

        $config = \RWS\Gearman\Helper\Utils::getGearmanConfig();
        $mail   = new \PHPMailer();

        $mail->isSMTP();
        $mail->Host     = $config['SendEmail']['smtp_host'];
        $mail->Port     = $config['SendEmail']['smtp_port'];
        $mail->SMTPAuth = false;

        $mail->From     = $workload['email_from'];
        $mail->FromName = $workload['name_from'];
        $mail->addReplyTo($workload['email_from'], $workload['name_from']);
        $mail->addAddress($workload['email_to'],   $workload['name_to']);

        $mail->isHTML(true);
        $mail->Subject  = $workload['subject'];
        $mail->Body     = $workload['body'];
        $mail->AltBody  = $workload['text'];

        $mail->addAttachment($workload['file_path'], $workload['file_name']);

        if (!$mail->send()) {
            throw new \Exception('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
        }

        return true;
    }

}
