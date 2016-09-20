<?php

namespace Api\Account\Billing\BillingReports;

/**
 * Class GW2AllAccounts
 * @package Api\Account\Billing\BillingReports
 */
class GW2AllAccounts
{

    private static $brokerage_account_ids = [];
    private static $option_packages       = [];
    private static $a_la_carte_options    = [];
    private static $packages_assigned_by  = [];
    private static $old_account_assigned  = [];

    /**
     * @return array
     */
    public static function get_headers() {
        return [
            'Account ID',
            'Is Brokerage',
            'Brokerage Name',
            'Pencode',
            'Base Monthly Value',
            'A-la-Carte Monthly Value',
            'Total Agent Accounts',
            'Base Agent Account Value',
            'Total Base Agent Account Value',
            'Total Monthly Value',
            'Total Premium Agent Account Spend',
            'Total Reselling Brokerage Value',
            'Username',
            'First Name',
            'Last Name',
            'Activated At',
            'Booking Date',
            'Last Charge Date',
            'Charge Code',
            'Package Name',
            'Billing Start Date',
            'Billing End Date',
            'Last Processed Date',
            'Grandfather Plan',
            'Phone',
            'Is Annual',
            'Enabled A La Carte',
            'Sales Rep',
            'A/R Hold',
            'Unearned Revenue',
            'Receivable'
        ];
    }

    /**
     * @param array $params
     * @return array
     */
    public static function get_data($params) {

        unset($params);
        $dbh        = \Propel::getConnection('account_licenses');

        self::setOptionPackagesArray($dbh);
        self::setALaCarteOptionsArray($dbh);
        self::setPackagesAssignedByArray($dbh);
        self::setOldAccountAssignedArray($dbh);

        $query      = "SELECT account_licenses.packages_account.account_id, " .
                          "'no' AS is_brokerage, " .
                          "IF((SELECT name FROM mls_info.brokerage WHERE mls_info.brokerage.account_id=rws_new.account.id LIMIT 1) IS NOT NULL," .
                             "(SELECT name FROM mls_info.brokerage WHERE mls_info.brokerage.account_id=rws_new.account.id LIMIT 1)," .
                             "(SELECT brokerage.name FROM mls_info.brokerage, mls_info.brokerage_location WHERE brokerage.id=brokerage_location.brokerage_id AND " .
                                 "rws_new.account.brokerage_location_id=brokerage_location.id LIMIT 1)) AS brokerage_name, " .
                          "IF((SELECT pencode FROM mls_info.brokerage, mls_info.brokerage_location WHERE brokerage.id=brokerage_location.brokerage_id AND " .
                                "rws_new.account.brokerage_location_id=brokerage_location.id LIMIT 1) IS NOT NULL," .
                             "(SELECT pencode FROM mls_info.brokerage, mls_info.brokerage_location WHERE brokerage.id=brokerage_location.brokerage_id AND " .
                            "rws_new.account.brokerage_location_id=brokerage_location.id LIMIT 1),'') AS pencode, " .
                          "'' AS base_monthly_value, " .
                          "'' AS a_la_carte_monthly_value, " .
                          "'0' AS total_agent_accounts, " .
                          "'' AS base_agent_account_value, " .
                          "'' AS total_base_agent_account_value, " .
                          "'' AS total_monthly_value, " .
                          "'' AS total_premium_agent_account_spend, " .
                          "'' AS total_reselling_brokerage_value, " .
                          "rws_new.user.username, " .
                          "rws_new.user.first_name, " .
                          "rws_new.user.last_name, " .
                          "rws_new.account.activated_at, " .
                          "'' AS booking_date, " .
                          "account_licenses.packages_account.last_charge_date, " .
                          "GROUP_CONCAT(option_packages.package_name SEPARATOR ', ') AS package_name, " .
                          "account_licenses.account_billing_status.billing_start_date, " .
                          "account_licenses.account_billing_status.billing_end_date, " .
                          "account_licenses.account_billing_status.last_processed_date, " .
                          "IF(account_licenses.account_billing_status.grandfather_plan=1,'yes','no') as grandfather_plan, " .
                          "rws_new.user.phone, " .
                          "GROUP_CONCAT(option_packages.is_annual SEPARATOR ', ') AS is_annual, " .
                          "'' AS enabled_a_la_carte, " .
                          "'' as sales_rep, " .
                          "'' AS a_r_hold, " .
                          "'' AS unearned_revenue, " .
                          "'' AS receivable, " . // report fields end here

                          "SUM(account_licenses.option_packages.cost) AS monthly_value, " .
                          "(SELECT CONCAT(IF(option_1= 1,'1,',''), " .
                                         "IF(option_2= 1,'2,',''), " .
                                         "IF(option_3= 1,'3,',''), " .
                                         "IF(option_3= 1,'4,',''), " .
                                         "IF(option_14= 1,'14,',''), " .
                                         "IF(option_15= 1,'15,',''), " .
                                         "IF(option_16= 1,'16,',''), " .
                                         "IF(option_17= 1,'17,',''), " .
                                         "IF(option_21= 1,'21,',''), " .
                                         "IF(option_22= 1,'22,',''), " .
                                         "IF(option_23= 1,'23,',''), " .
                                         "IF(option_28= 1,'28,',''), " .
                                         "IF(option_31= 1,'31,',''), " .
                                         "IF(option_32= 1,'32,','') " .
                           ") FROM option_account WHERE account_id=packages_account.account_id LIMIT 1) as account_options, " .
                          "option_packages.rws_gftr_cost as grandfather_plan_cost, " .
                          "GROUP_CONCAT(option_packages.package_id) AS package_id, " .
                          "IF((option_packages.package_id=102 || option_packages.package_id=112),'yes','no') AS old_brokerage, " .
                          "IF((option_packages.reseller_plan<>''),'yes','no') AS new_brokerage " .
                      "FROM rws_new.account " .
                      "JOIN account_licenses.packages_account USE INDEX (account_id) ON (account.id = packages_account.account_id) " .
                      "JOIN account_licenses.account_billing_status ON (packages_account.account_id=account_billing_status.account_id AND account_billing_status.billing_enabled = 1)  " .
                      "JOIN rws_new.user ON (account.id = user.account_id AND rws_new.user.role = 1)  " .
                      "JOIN account_licenses.option_packages ON (packages_account.package_id=option_packages.package_id) " .
                      "WHERE rws_new.account.deactivated_at IS NULL  " .
                      "GROUP BY packages_account.account_id LIMIT 100";// LIMIT 100

        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        self::insertIsBrokerage($rows);
        self::insertTotalAgentAccounts($rows, $dbh);
        self::insertBaseAccountValue($rows);
        self::insertALaCarteOptions($rows);
        self::insertBrokerageValues($rows);
        self::insertTotalMonthlyValue($rows);

        return $rows ? $rows : [];
    }

    private static function setOptionPackagesArray(&$dbh) {

        $query                      = "SELECT * FROM account_licenses.option_packages";
        $statement                  = $dbh->query($query);
        self::$option_packages      = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function setALaCarteOptionsArray(&$dbh) {

        $query                      = "SELECT * FROM account_licenses.activation_options";
        $statement                  = $dbh->query($query);
        self::$a_la_carte_options   = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function setPackagesAssignedByArray(&$dbh) {

        $query                      = "SELECT count(*) as count_assigned, assigned_by_account, package_id " .
                                      "FROM account_licenses.packages_account " .
                                      "WHERE package_id IN (3002,3003) " .
                                      "GROUP BY assigned_by_account, package_id " .
                                      "ORDER BY assigned_by_account";
        $statement                  = $dbh->query($query);
        self::$packages_assigned_by = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function setOldAccountAssignedArray(&$dbh) {
        $query                      = "SELECT count(*) as count_accounts, packages_account.package_id, option_packages.is_annual, option_packages.cost, option_packages.brokerage_account_id " .
                                      "FROM packages_account, option_packages, account_billing_status " .
                                      "WHERE option_packages.brokerage_account_id>0 " .
                                      "AND packages_account.package_id=option_packages.package_id " .
                                      "AND packages_account.account_id=account_billing_status.account_id " .
                                      "AND account_billing_status.billing_enabled=1 " .
                                      "GROUP BY packages_account.package_id " .
                                      "ORDER BY packages_account.package_id";
        $statement                  = $dbh->query($query);
        self::$old_account_assigned = $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function insertBrokerageValues(&$rows) {

        $dbh        = \Propel::getConnection('mls_info');

        $query      = "SELECT count(*) as cnt_accounts, brokerage.account_id " .
                      "FROM mls_info.brokerage, mls_info.brokerage_location, rws_new.account " .
                      "WHERE mls_info.brokerage.account_id IN (" . implode(',', self::$brokerage_account_ids) . ") " .
                      "AND mls_info.brokerage_location.brokerage_id = mls_info.brokerage.id " .
                      "AND rws_new.account.brokerage_location_id=brokerage_location.id " .
                      "GROUP BY brokerage.account_id";

        $statement  = $dbh->query($query);
        $brokerages = $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$arr) {
            if ($arr['is_brokerage'] = 'yes') {
                foreach ($brokerages as $brokerage) {
                    if ($brokerage['account_id'] == $arr['account_id']) {
                        $arr['total_agent_accounts'] = $brokerage['cnt_accounts'];
                        // base_agent_account_value && total_base_agent_account_value
                        $arr_packages     = explode(",", $arr['package_id']);
                        foreach ($arr_packages as $package_id) {
                            foreach (self::$option_packages as $packages) {
                                if ($packages['package_id'] == $package_id ) {
                                    if ($packages['reseller_plan'] == "ASPGWB") {
                                        $arr['base_agent_account_value'] = $arr['total_agent_accounts'] > 100
                                            ? 1.5
                                            : ($arr['total_agent_accounts'] > 20 ? 1.75 : 2);
                                        $arr['total_base_agent_account_value'] = $arr['base_agent_account_value'] * $arr['total_agent_accounts'];
                                    }
                                    break 2;
                                }
                            }
                        }
                        //total_premium_agent_account_spend
                        // part 1 - check brokerages on 2001, 2002, 2003 packages
                        foreach (self::$packages_assigned_by as $assigned_packages) {
                            if ($assigned_packages['account_id'] == $arr['account_id']) {
                                $arr['total_premium_agent_account_spend'] += self::getTotalPremiumAgentAccountSpend($assigned_packages['count_assigned'], $assigned_packages['package_id']);
                            } else if ($arr['total_premium_agent_account_spend'])
                                break;
                        }
                        // part 2 - check old brokerage packages / individual agent packages
                        if (!$arr['total_premium_agent_account_spend']) {
                            foreach (self::$old_account_assigned as $packages) {
                                if ($packages['brokerage_account_id'] == $arr['account_id']) {
                                    $arr['total_premium_agent_account_spend'] =
                                        $packages['count_accounts'] * ($packages['is_annual'] ? round($packages['cost'] / 12, 2) : $packages['cost']);
                                    break;
                                }
                            }
                        }

                        $arr['total_reselling_brokerage_value'] = $arr['total_reselling_brokerage_value'] +
                                                                  $arr['monthly_value'] +
                                                                  $arr['total_premium_agent_account_spend'];

                        break; //  jump out of $brokerages foreach
                    }
                }
            }
        }
    }

    private static function getTotalPremiumAgentAccountSpend($cnt_assigned, $package_id) {

        foreach (self::$option_packages as $packages) {
            if ($packages['package_id'] == $package_id)
                return $cnt_assigned * $packages['reseller_cost_level_' . ($cnt_assigned > 100 ? 3 : ($cnt_assigned > 20 ? 2 : 1))];
        }

        return 0;
    }

    private static function insertTotalMonthlyValue(&$rows) {

        foreach ($rows as &$arr) {
            $arr['monthly_value'] = $arr['monthly_value'] +
                                    $arr['total_base_agent_account_value'] +
                                    $arr['a_la_carte_monthly_value'];
        }
    }

    private static function insertIsBrokerage(&$rows) {

        foreach ($rows as &$arr) {
            if ($arr['old_brokerage'] == 'yes' || $arr['new_brokerage'] == 'yes') {
                $arr['is_brokerage'] = 'yes';
                self::$brokerage_account_ids[] = $arr['account_id'];
            }
        }
    }

    private static function insertALaCarteOptions(&$rows) {

        foreach ($rows as &$arr) {
            if ($arr['account_options']) {
                $arr_options     = explode(",", substr($arr['account_options'], 0, -1));
                $options_caption = [];
                $options_cost    = 0;
                foreach ($arr_options as $option) {
                    if (self::optionNotInPackages($option,$arr['package_id'])) {
                        $options_caption[] = self::getALaCarteOptionCaption($option);
                        $options_cost     += self::getALaCarteMonthlyCost($option);
                    }
                }

                if ($options_cost) {
                    $arr['enabled_a_la_carte']       = implode(',', $options_caption);
                    $arr['a_la_carte_monthly_value'] = $options_cost;
                }
            }
        }
    }

    private static function getALaCarteMonthlyCost($option) {
        foreach (self::$a_la_carte_options as $options) {
            if ($options['id'] == $option)
                return $options['is_annual'] ? round($options['cost'] / 12, 2) : $options['cost'];
        }

        return 0;
    }

    private static function getALaCarteOptionCaption($option) {
        foreach (self::$a_la_carte_options as $options) {
            if ($options['id'] == $option)
                return $options['caption'];
        }

        return "";
    }

    private static function optionNotInPackages($option, $package_ids) {
        $arr_packages     = explode(",", $package_ids);
        foreach ($arr_packages as $package_id) {
            foreach (self::$option_packages as $packages) {
                if ($packages['package_id'] == $package_id) {
                    if ($packages["option_" . $option] == 1)
                        return false;
                    break;
                }
            }
        }

        return true;
    }

    private static function insertTotalAgentAccounts(&$rows, &$dbh) {

        $query      = "SELECT count(*) as agent_accounts_count, brokerage.account_id as account_id " .
                      "FROM mls_info.brokerage, mls_info.brokerage_location, rws_new.account " .
                      "WHERE mls_info.brokerage.account_id IN (" . implode(',', self::$brokerage_account_ids) . ") " .
                      "AND mls_info.brokerage_location.brokerage_id = mls_info.brokerage.id " .
                      "AND rws_new.account.brokerage_location_id=brokerage_location.id " .
                      "GROUP BY brokerage.account_id";

        $statement  = $dbh->query($query);
        $agent_rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($agent_rows as $arr_agents) {
            foreach ($rows as &$arr) {
                if ($arr['account_id'] == $arr_agents['account_id']) {
                    $arr['total_agent_accounts'] = $arr_agents['agent_accounts_count'];
                    break;
                }
            }
        }
    }

    private static function insertBaseAccountValue(&$rows) {

        foreach ($rows as &$arr) {

            if ($arr['grandfather_plan'] == 'yes' && $arr['grandfather_plan_cost'] != '0.00') {
                $arr['base_monthly_value'] = $arr['grandfather_plan_cost'];
            } else {
                $arr_packages     = explode(",", $arr['package_id']);
                $monthly_cost_sum = 0;
                foreach ($arr_packages as $package_id) {
                    if (!in_array($package_id, [3001, 3002, 3003])) {
                        $monthly_cost_sum += self::getPackageValue($package_id,"is_annual")
                            ? round(self::getPackageValue($package_id,"cost") / 12, 2)
                            : self::getPackageValue($package_id,"cost");
                    }
                }
                $arr['base_monthly_value'] = $monthly_cost_sum;
            }
        }
    }

    private static function getPackageValue($package_id,$field_name) {

        foreach(self::$option_packages as $arr) {
            if ($arr['package_id'] == $package_id)
                return $arr[$field_name];
        }

        return 0;
    }

    private static function insertMissingValues( &$rows, $arr_new_vals, $field_name) {

        foreach ($rows as &$arr) {
            foreach ($arr_new_vals as $arr_values) {
                if ($arr_values['account_id'] == $arr['account_id']) {
                    $arr[$field_name] =  $arr_values[$field_name];
                    if ($field_name == "agent_accounts") {
                        $arr['agent_accounts_value'] =  $arr_values['agent_accounts_value'];
                        $arr['total_value'] =  $arr['monthly_value'] + $arr['agent_accounts_value'];
                    }
                    break;
                }
            }
        }
    }

    private  static function getReceivables($account_ids) {

        $dbh        = \Propel::getConnection('accounting');

        $query      = "SELECT sum(sub_total) as receivable, id_num as account_id " .
                      "FROM invoices WHERE id_num IN (" . implode(',', $account_ids) . ") AND `status` = 0 GROUP BY id_num";

        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    private  static function getARHold($account_ids) {

        $dbh        = \Propel::getConnection('accounting');

        $query      = "SELECT a_r_hold, id_num as account_id FROM billing WHERE id_num IN (" . implode(',', $account_ids) . ")";

        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    private  static function getSalesReps($account_ids) {

        $dbh        = \Propel::getConnection('accounting');

        $query      = "SELECT CONCAT(referral_representatives.first_name,' ',referral_representatives.last_name) as sales_rep, account_id " .
                      "FROM referral_account JOIN referral_representatives ON referral_representatives.id=referral_account.representative_id " .
                      "WHERE referral_account.account_id IN (" . implode(',', $account_ids) . ")";

        $statement  = $dbh->query($query);
        $rows       = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

}
