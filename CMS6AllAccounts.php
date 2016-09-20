<?php

namespace Api\Account\Billing\BillingReports;

/**
 * Class CMS6AllAccounts
 * @package Api\Account\Billing\BillingReports
 */
class CMS6AllAccounts
{

    private static $dbh_cm6   = null;

    /**
     * @return array
     */
    public static function get_headers() {
        return [
            'Id',
            'Is Brokerage',
            'Brokerage Name',
            'Name',
            'Phone',
            'Email',
            'Address',
            'Town',
            'Postal Code',
            'Country',
            'Province',
            'Prev Hosting Date',
            'Hosting Package Name',
            'Monthly Value',
            'Agents Total',
            'Total Premium Agent Account Spend',
            'Annual Value',
            'Total Reselling Brokerage Value',
            'Months Left',
            'Left Value',
            'Receivable',
            'Number of assotiated agent accounts',
        ];
    }

    /**
     * @param array $params
     * @return array
     */
    public static function get_data($params) {

        $dbh        = self::connectCM6();

        unset($params);
        $query      = "SELECT brokerage_id, id_num, brokerage_name FROM toronto.brokerage";
        $statement  = $dbh->query($query);
        $brokerages = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $query      = "SELECT id, description, cycle, cost, resellingBrokerageBestPrice AS resellingBrokerage, Century21Parkland, CreditValley, RemaxWest FROM accounting.hosting_packages";
        $statement  = $dbh->query($query);
        $packages   = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $query      = "SELECT " .
                      "t1.id_num, " .
                      "'no' AS is_brokerage, " .
                      "'' AS brokerage_name, " .
                      "t1.adminName, " .
                      "t1.adminPhone, " .
                      "t1.adminEmail, " .
                      "t2.address, " .
                      "t2.town, " .
                      "t2.postal, " .
                      "t2.country, " .
                      "t2.province, " .
                      "t2.prev_hosting_date, " .
                      "'' AS hosting_package_name, " .
                      "'' AS monthly_value, " .
                      "'' AS total_agents_in_brokerage, " .
                      "'' AS total_premium_agent_account_spend, " .
                      "'' AS annual_value, " .
                      "'' AS total_reselling_brokerage_value, " .
                      "'' AS left_months, " .
                      "'' AS left_value, " .
                      "(IF(t2.hosting_package=20,0,(SELECT SUM(sub_total) AS subt FROM accounting.invoices AS t6 WHERE t6.id_num = t1.id_num AND t6.`status` = 0))) AS receivable, " .
                      "'0' AS child_accounts, " .
                      "IF(t1.is_master=1,(CASE WHEN t1.id_num IN (20791,20413) THEN 'RemaxWest' " .
                          "WHEN t1.id_num IN (10526) THEN 'Century21Parkland' " .
                          "WHEN t1.id_num IN (11102) THEN 'CreditValley' " .
                          "ELSE 'resellingBrokerage' END),'') AS reseller_col_1, " .
                      "IF(t1.master_account!=0,(CASE WHEN t1.master_account IN (20791,20413) THEN 'RemaxWest' " .
                          "WHEN t1.master_account IN (10526) THEN 'Century21Parkland' " .
                          "WHEN t1.master_account IN (11102) THEN 'CreditValley' " .
                          "ELSE 'resellingBrokerage' END),'') AS reseller_col_2, " .
                      "t2.hosting_package, " .
                      "t1.is_master, " .
                      "t1.master_account, " .
                      "t1.companyName, " .
                      "t1.brokerageAccess " .
                      "FROM realwebleads.userInfo AS t1, accounting.billing AS t2 " .
                      "WHERE t1.id_num=t2.id_num " .
                      "AND t1.active = 1 " .
                      "AND t2.active = 1 " .
                      "AND t2.demo_account = 0 " .
                      "AND t2.billable_account = 1 ORDER BY t1.id_num";

        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        self::insertAgentsData($rows, $packages);
        self::insertPackagesData($rows, $packages);
        self::insertBrokerageInfo($rows, $brokerages);
        self::insertBrokerageName($rows, $brokerages);

        return $rows ? self::getShortenedArray($rows) : [];
    }

    private static function insertAgentsData(&$rows, &$arr_packages) {

        $master_account_ids     = self::getMasterAccountIds($rows);
        $arr_agents_packages    = self::getAgentsTotalInfo($master_account_ids);

        foreach ($rows as &$arr) {
            if ($arr['is_master']) {
                $agent_account_spend = $child_accounts = $rate = $cycle = 0;
                foreach ($arr_agents_packages as $agents_packages) {
                    if ($agents_packages['master_account'] == $arr['id_num']) {
                        $rate                 = self::getHostingPackageData($arr_packages, $agents_packages['hosting_package'], $arr['reseller_col_1']);
                        $cycle                = self::getHostingPackageData($arr_packages, $agents_packages['hosting_package'], "cycle");
                        $child_accounts      += $agents_packages['agents_package_count'];
                        $agent_account_spend += $agents_packages['agents_package_count'] *
                                                ($cycle == 12 ? round($rate / 12, 2) : $rate);
                    }
                }
                $arr['child_accounts']                    = $child_accounts;
                $arr['total_premium_agent_account_spend'] = $agent_account_spend;
            }
        }
    }

    private static function getMasterAccountIds(&$rows) {

        $master_account_ids = [];

        foreach ($rows as $arr) {
            if ($arr['is_master'])
                $master_account_ids[] = $arr['id_num'];
        }

        return $master_account_ids;
    }

    private static function getAgentsTotalInfo(&$master_account_ids) {

        $dbh        = self::connectCM6();
        $query      = "SELECT count(*) AS agents_package_count, t1.id_num, t1.hosting_package, t2.master_account " .
                      "FROM accounting.billing t1, realwebleads.userInfo t2 " .
                      "WHERE t2.master_account IN (" . implode(',', $master_account_ids). ") " .
                      "AND t2.id_num=t1.id_num " .
                      "AND t2.active = 1 " .
                      "AND t1.active = 1 " .
                      "AND t1.demo_account = 0 " .
                      "AND t1.billable_account = 1 " .
                      "GROUP BY t2.master_account, t1.hosting_package";
        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    private static function getHostingPackageData(&$arr_packages, $hosting_package, $return_field_name) {

        foreach ($arr_packages as $arr) {
            if ($arr['id'] == $hosting_package)
                return $arr[$return_field_name];
        }

        return 0;
    }

    private static function insertPackagesData(&$rows, &$arr_packages) {

        foreach ($rows as &$arr) {

            foreach ($arr_packages as $package) {

                if ($package['id'] == $arr['hosting_package']) {
                    $arr['hosting_package_name'] = $package['description'];
                    $arr['monthly_value']        = $package[$arr['reseller_col_2'] ? : 'cost'];
                    $arr['annual_value']         = $package['cycle'] == 12
                        ? ($arr['reseller_col_2'] ? $package[$arr['reseller_col_2']] : $package['cost'])
                        : 0;

                    if ($arr['reseller_col_1'])
                        $arr['total_reselling_brokerage_value'] = $arr['monthly_value'] + $arr['total_premium_agent_account_spend'];

                    $arr['left_months'] = $arr['annual_value'] ? self::getLeftMonths($arr['prev_hosting_date']) : 0;
                    $arr['left_value']  = $arr['annual_value'] ? ($arr['annual_value'] / 12) * $arr['left_months'] : 0;

                    break;
                }
            }
        }
    }

    private static function getLeftMonths($prev_hosting_date) {

        $arr_prev_hosting_date  = explode('-', $prev_hosting_date);
        $prev_month             = intval($arr_prev_hosting_date[0]);
        $prev_year              = $arr_prev_hosting_date[1];
        $cur_month              = date('n');

        return $prev_year == (date('Y') - 1) ? $prev_month - $cur_month : ($prev_year == date('Y') ? $prev_month + 12 - $cur_month : 0);
    }

    private static function insertBrokerageInfo( &$rows, &$arr_brokerages) {

        $brokerage_packages = [8, 11, 12, 16];

        foreach ($rows as &$arr) {

            if (in_array($arr['hosting_package'],$brokerage_packages))
                $arr['is_brokerage'] = 'yes';

            if ($arr['is_brokerage'] == 'no') {

                foreach ($arr_brokerages as $brokerage) {
                    if ($brokerage['id_num'] && $brokerage['id_num'] == $arr['id_num']) {
                        $arr['is_brokerage'] = 'yes';
                        break;
                    }
                }
            }

            if ($arr['is_brokerage'] == 'yes')
                $arr['total_agents_in_brokerage'] = self::getAgentsCount($arr['id_num']);
        }
    }

    private static function getAgentsCount($id_num) {

        $dbh        = self::connectCM6();
        $query      = "SELECT count(*) as cnt FROM " . "u" . $id_num. ".contacts";
        $statement  = $dbh->query($query);
        $row        = $statement ? $statement->fetch(\PDO::FETCH_ASSOC) : null;

        return $row ? $row['cnt'] : 0;
    }

    private static function insertBrokerageName( &$rows, &$arr_brokerages) {

        foreach ($rows as &$arr) {
            if (!$arr['brokerageAccess']) {
                $arr['brokerage_name'] = $arr['companyName'];
                continue;
            }
            $boo_found = false;
            foreach ($arr_brokerages as $brokerage) {

                if ($brokerage['brokerage_id'] == $arr['brokerageAccess']) {
                    $arr['brokerage_name'] = $brokerage['brokerage_name'];
                    $boo_found = true;
                    break;
                }
            }
            if (!$boo_found)
                $arr['brokerage_name'] = $arr['companyName'];
        }
    }

    private static function getShortenedArray($rows) {

        $result = [];
        $arr_extra_columns = ['reseller_col_1','reseller_col_2','hosting_package','is_master','master_account','companyName','brokerageAccess'];

        foreach ($rows as &$arr)
            $result[] = array_diff_key($arr, array_flip($arr_extra_columns));

        return $result;
    }

    public static function connectCM6()
    {
        if (!self::$dbh_cm6)
            self::$dbh_cm6 = \Propel::getConnection('accounting');

        return self::$dbh_cm6;
    }

}
