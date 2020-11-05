<?php

if (class_exists('Rcl_Payment')) {

add_action('init','rcl_add_perfect_money_payment');
function rcl_add_perfect_money_payment(){
    $pm = new Rcl_PM_Payment();
    $pm->register_payment('perfect-money');
}

class Rcl_PM_Payment extends Rcl_Payment{

    public $form_pay_id;

    function register_payment($form_pay_id){
        $this->form_pay_id = $form_pay_id;
        parent::add_payment($this->form_pay_id, array(
            'class'=>get_class($this),
            'request'=>'PM_TYPE_PAY',
            'name'=>'Perfect Money',
            'image'=>rcl_addon_url('assets/perfect-money.jpg',__FILE__)
            ));
        if(is_admin()) $this->add_options();
    }

    function add_options(){
        add_filter('rcl_pay_option',(array($this,'options')));
        add_filter('rcl_pay_child_option',(array($this,'child_options')));
    }

    function options($options){
        $options[$this->form_pay_id] = 'Perfect Money';
        return $options;
    }

    function child_options($child){
        global $rmag_options;

        $opt = new Rcl_Options();

        $curs = array( 'USD', 'EUR' );

        if(false !== array_search($rmag_options['primary_cur'], $curs)) {
            $options = array(
                array(
                    'type' => 'text',
                    'slug' => 'pm_account',
                    'title' => __('Account ID (Например U123456)')
                ),
                array(
                    'type' => 'text',
                    'slug' => 'pm_name',
                    'title' => __('Имя аккаунта')
                ),
                array(
                    'type' => 'text',
                    'slug' => 'pm_phrase',
                    'title' => __('Кодовая фраза')
                )
            );
        }else{
            $options = array(
                array(
                    'type' => 'custom',
                    'slug' => 'notice',
                    'notice' => __('<span style="color:red">Данное подключение не поддерживает действующую валюту сайта.<br>'
                        . 'Поддерживается работа с USD, EUR</span>')
                )
            );
        }

        $child .= $opt->child(
            array(
                'name'=>'connect_sale',
                'value'=>$this->form_pay_id
            ),
            array(
                $opt->options_box( __('Настройки подключения Perfect Money'), $options)
            )
        );

        return $child;
    }

    function pay_form($data){
        global $rmag_options;

        $pm_account = $rmag_options['pm_account'];
        $pm_name = $rmag_options['pm_name'];

        $currency = $rmag_options['primary_cur'];

        $baggage_data = ($data->baggage_data)? $data->baggage_data: false;

        $fields = array(
            'PAYEE_ACCOUNT'=>$pm_account,
            'PAYEE_NAME'=>$pm_name,
            'PAYMENT_ID'=>$data->pay_id,
            'PAYMENT_AMOUNT'=>$data->pay_summ,
            'PAYMENT_UNITS'=>$currency,
            'STATUS_URL'=>get_permalink($rmag_options['page_result_pay']),
            'PAYMENT_URL'=>get_permalink($rmag_options['page_success_pay']),
            'PAYMENT_URL_METHOD'=>"POST",
            'NOPAYMENT_URL'=>get_permalink($rmag_options['page_fail_pay']),
            'NOPAYMENT_URL_METHOD'=>"LINK",
            'PM_TYPE_PAY'=>$data->pay_type,
            'PM_USER_ID'=>$data->user_id,
            'PM_BAGGAGE_DATA'=>$baggage_data,
            'BAGGAGE_FIELDS'=>"PM_TYPE_PAY PM_USER_ID PM_BAGGAGE_DATA"
        );

        $form = parent::form($fields,$data,"https://perfectmoney.is/api/step1.asp");

        return $form;
    }

    function result($data){
        global $rmag_options;

        $pm_phrase = $rmag_options['pm_phrase'];

        define('ALTERNATE_PHRASE_HASH', strtoupper(md5($pm_phrase)));

        $array = array(
            $_REQUEST['PAYMENT_ID'],
            $_REQUEST['PAYEE_ACCOUNT'],
            $_REQUEST['PAYMENT_AMOUNT'],
            $_REQUEST['PAYMENT_UNITS'],
            $_REQUEST['PAYMENT_BATCH_NUM'],
            $_REQUEST['PAYER_ACCOUNT'],
            ALTERNATE_PHRASE_HASH,
            $_REQUEST['TIMESTAMPGMT']
        );

        $hash = strtoupper(md5(implode(':',$array)));

        if($hash!=$_REQUEST['V2_HASH']){
            rcl_mail_payment_error($hash);
            die('HASH failed.');
        }

        $data->pay_summ = $_REQUEST['PAYMENT_AMOUNT'];
        $data->pay_id = $_REQUEST['PAYMENT_ID'];
        $data->user_id = $_REQUEST['PM_USER_ID'];
        $data->pay_type = $_REQUEST['PM_TYPE_PAY'];
        $data->baggage_data = $_REQUEST['PM_BAGGAGE_DATA'];

        if(!parent::get_pay($data)){
            parent::insert_pay($data);
            die('PAYMENT OK.');
        }
    }

    function success(){
        global $rmag_options;

        $data['pay_id'] = $_REQUEST['PAYMENT_ID'];
        $data['user_id'] = $_REQUEST['PM_USER_ID'];

        if(parent::get_pay((object)$data)){
            wp_redirect(get_permalink($rmag_options['page_successfully_pay'])); exit;
        } else {
            wp_die('Платеж не найден в базе данных');
        }

    }

}

}
