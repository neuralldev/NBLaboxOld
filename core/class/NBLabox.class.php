<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class CurlRequest {

    private $ch;

    /**
     * Init curl session
     * 
     * $params = array('url' => '',
     *                    'host' => '',
     *                   'header' => '',
     *                   'method' => '',
     *                   'referer' => '',
     *                   'cookie' => '',
     *                   'post_fields' => '',
     *                    ['login' => '',]
     *                    ['password' => '',]      
     *                   'timeout' => 0
     *                   );
     */
    public function __construct($params) {
        $options = array( 
	    CURLOPT_RETURNTRANSFER => true, // to return web page
            CURLOPT_HEADER         => true, // to return headers in addition to content
            CURLOPT_FOLLOWLOCATION => true, // to follow redirects
            CURLOPT_ENCODING       => "",   // to handle all encodings
            CURLOPT_AUTOREFERER    => true, // to set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,  // set a timeout on connect
            CURLOPT_TIMEOUT        => 120,  // set a timeout on response
            CURLOPT_MAXREDIRS      => 10,   // to stop after 10 redirects
            //CURLINFO_HEADER_OUT    => true, // no header out
            CURLOPT_SSL_VERIFYPEER => 0,// to disable SSL Cert checks
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEJAR      =>$params['cookiejar'],
            CURLOPT_COOKIEFILE     =>$params['cookiejar'],
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_VERBOSE        => 1,
        );
        $this->ch = curl_init($params['url']);
        curl_setopt_array( $this->ch, $options );
 
        if ($params['port'])
            curl_setopt($this->ch, CURLOPT_PORT, $params['port']);

        if ($params['method'] == "POST") {
            curl_setopt($this->ch, CURLOPT_POST, TRUE);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params['post_fields']);
        }        
    }

    /**
     * Execute curl request
     *
     * @return array  'header','body','curl_error','http_code','last_url'
     */
    public function exec() {
        $response = curl_exec($this->ch);
        $error = curl_error($this->ch);
        $result = array('header' => '',
            'body' => '',
            'curl_error' => '',
            'http_code' => '',
            'last_url' => '');
        if ($error != "") {
            $result['curl_error'] = $error;
            return $result;
        }

        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $result['header'] = substr($response, 0, $header_size);
        $result['body'] = substr($response, $header_size);
        $result['http_code'] = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $result['last_url'] = curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL);
        return $result;
    }

}

class LaBox {

    private $curl, $param, $login, $password, $isloggeddIn, $fetched, $fetchresult;

    //put your code here
    public function __construct($param) {
        // set a unique cookie jar file for the whole session
        $param['cookie']=1;
        $param['cookiejar']= tempnam ("/tmp", "CURLCOOKIE");
                log::add('NBLabox', 'debug', 'grant cookie '.$param['cookiejar']);
        $this->param = $param;
        $this->curl = new CurlRequest($param);
        $this->fetched = FALSE;
    }

    public function __destruct() {
        if ($this->isloggeddIn == 1) {
            log::add('NBLabox', 'debug', 'try to logout');
            $this->doLogout();
        }
            // destroy cookie file
        try{
            log::add('NBLabox', 'debug', 'delete cookie file '.$this->param['cookiejar']);
            unlink($this->param['cookiejar'] );
        }
        catch(Exception $e) {
            
        }
    }

    /*
     * fetch page content, store it in ^curl variable with state variables returned by curl function
     */

    public function exec() {

        try {
            $result = $this->curl->exec();
            if ($result['curl_error'])
                return FALSE;
            if ($result['http_code'] != '200')
                return FALSE;
            if (!$result['body'])
                return FALSE;
        } catch (Exception $e) {
//                    echo 'debug : '.$e->getMessage();
            return FALSE;
        }
        $this->fetched = TRUE;
        return (($this->fetchresult = array("header" => $result['header'], "body" => $result['body'])));
    }

    /*
     * returns TRUE is this is a LaBox system otherwhise FALSE
     */

    public function autoDetect() {
        $host = parse_url($this->param['url'], PHP_URL_HOST);
            $errno = 0;
            $errstr = "";
            $fp = @fsockopen($host, $this->param['port'], $errno, $errstr, 10); // check regular http port
            if ($fp == FALSE) {
                log::add('NBLabox', 'debug', 'cant open socket on port '.$host.':'.$this->param['port']);
                return FALSE;
            }
            if (is_resource($fp)) { // found
                fclose($fp);
                // now check if this is LaBox by getting index page content and analyse it
                // first read index page content
                if ($this->fetched == FALSE)
                    $result = $this->exec();
                ELSE
                    $result = $this->fetchresult;
                if ($result == FALSE) {
                    log::add('NBLabox', 'debug', 'page found on '.$host." but no readable content");
                    return FALSE; // if there is no readable content 
                }
// now seach for specific numericable information in it such as login form
                if (strpos($result['body'], "logo-num-HD") == FALSE) {
                    log::add('NBLabox', 'debug', 'page found on '.$host." but no numericable signature present ->".$result['body']);
                    return FALSE;
                } 
                return $this->fetchresult;
            } else
                log::add('NBLabox', 'debug', 'cant find ressource '.$host.':'.$this->param['port']);
        return FALSE;
        
    }

    public function getPage($url, $cookie) {
         $getparams = array(
            'url' => $url,
            'host' => '',
            'header' => '',
            'method' => 'GET', // 'POST','HEAD'
            'referer' => '',
            'cookie' =>1,
            'cookiejar' => $cookie,
            'post_fields' => '', 
            'timeout' => 20 
        );
        log::add('NBLabox', 'debug', __METHOD__ . ' get page '.$url);        
        $getpage = new CurlRequest($getparams);
        return  $getpage->exec();        
    }

    public function postPage($url, $postparams, $cookie) {
         $getparams = array(
            'url' => $url,
            'host' => '',
            'header' => '',
            'method' => 'POST', // 'POST','HEAD'
            'referer' => '',
            'cookie' =>1,
            'cookiejar' => $cookie,
            'post_fields' => $postparams, 
            'timeout' => 20 
        );
        log::add('NBLabox', 'debug', __METHOD__ . ' post page '.$url);        
        $getpage = new CurlRequest($getparams);
        return $getpage->exec();
    }
    
    public function doLogin($login, $password) {
//         get basic url from current request
        $urlitems = parse_url($this->param['url']);
        $result = $this->getPage('http://' . $urlitems['host'] . '/',  $this->param['cookiejar']);
        $result = $this->getPage('http://' . $urlitems['host'] . '/config.html',  $this->param['cookiejar']);
        $alreadyconnected = strpos($result['body'], 'TRY AGAIN');
        if ($alreadyconnected) {
            $result = $this->getPage('http://' . $urlitems['host'] . '/logout.html',  $this->param['cookiejar']);
            $result = $this->getPage('http://' . $urlitems['host'] . '/config.html',  $this->param['cookiejar']);            
            $result = $this->postPage('http://' . $urlitems['host'] . '/goform/login',  'loginUsername=' . $login .  '&loginPassword=' . $password, $this->param['cookiejar']);             
            $alreadyconnected = strpos($result['body'], 'TRY AGAIN');
            $result = $this->getPage('http://' . $urlitems['host'] . '/config.html',  $this->param['cookiejar']);            
        }
        if ($alreadyconnected) {
            log::add('NBLabox', 'warning', __METHOD__ . ' cannot logout, aborting');
            return FALSE;
        }
        $loggedin = strpos($result['body'], 'SE DECONNECTER');
        if ($loggedin) {
            // can reset
            return TRUE;
        } else {
            log::add('NBLabox', 'warning', __METHOD__ . ' cannot login, aborting '.  json_encode($result));
            return FALSE;
        }
        
//<form method="post" action="/goform/login" name="login">
//<input name="loginUsername" type="text" size="30" />
//<input name="loginPassword" type="password" size="30" maxlength="63" />
//<input type="button" class="num-button2" value="OK" id="checkPWD" onClick="return myDisableButton(this);" />
//</form>
    }

    public function doLogout() {
        $urlitems = parse_url($this->param['url']);
        $params = array(
            'url' => 'http://' . $urlitems['host'] . '/logout.html',
            'host' => '',
            'header' => '',
            'method' => 'GET', // 'POST','HEAD'
            'referer' => '',
            'cookie' =>1,
            'cookiejar' => $this->param['cookiejar'],
            'post_fields' => '', // 'var1=value&var2=value
            'timeout' => 20
        );
        log::add('NBLabox', 'debug', __METHOD__ . ' logout called with '.$params['cookiejar']);
//        log::add('NBLabox', 'debug', __METHOD__ . ' logout '.json_encode($params, true));
        $logout = new CurlRequest($params);
        $result = $logout->exec();
        if ($result == FALSE)
            return FALSE;
        //log::add('NBLabox', 'debug', __METHOD__ . ' logout '.json_encode($result, true));
        $this->isloggeddIn = 0;
        log::add('NBLabox', 'debug', __METHOD__ . ' logout page returned '.$result['http_code']);        
        RETURN TRUE;
    }

    public function restartModem() {
        if ($this->isloggeddIn == 0)
            return FALSE;
        $urlitems = parse_url($this->param['url']);
        log::add('NBLabox', 'debug', __METHOD__ . ' reset modem attempt '.json_encode($result, true));        
        $result = $this->postPage('http://' . $urlitems['host'] . '/goform/WebUiOnlyReboot',  '', $this->param['cookiejar']);            
        log::add('NBLabox', 'debug', __METHOD__ . ' reset modem returns '.json_encode($result, true));        
        return $result;
        
//<form action="/goform/WebUiOnlyReboot" method="post">
//<span class="num-button-wrapper">
//<span class="l"> </span>
//<span class="r"> </span>                                              
//<input type="submit" class="num-button" value="Redémarrer votre modem" align="middle" onclick="return rebootConfirm();" />
//</span>
//</form>
    }

    // extract value from tag
    private function extractValue($haystack, $needle, $tag) {
        $section = strpos($haystack, $needle);
        if ($section == FALSE)
            return FALSE;
        $td = strpos($haystack, '<' . $tag, $section);
        if ($td == FALSE)
            return FALSE;
        $closetag = strpos($haystack, '>', $td);
        if ($closetag == FALSE)
            return FALSE;
        $fintd = strpos($haystack, '</' . $tag . '>', $td);
        if ($fintd == FALSE)
            return FALSE;
        $longueur = $fintd - $closetag - 1;
        $val = substr($haystack, $closetag + 1, $longueur);
        return $val;
    }

    private function fetch() {
//        echo "debug : " . ($this->fetched == TRUE ? "TRUE" : "FALSE") . "<br/>";
        //echo "debug : ".($this->fetchresult)."<br/>";
        if ($this->fetched == FALSE)
            return $this->exec();
        ELSE
            return $this->fetchresult;
    }

    public function getPublicIPAddress() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        return $this->extractValue($result['body'], "Votre adresse IP", 'td');
    }

    public function getMask() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        return $this->extractValue($result['body'], "Votre masque de sous", 'td');
    }

    public function getDefaultGateway() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        return $this->extractValue($result['body'], "Votre passerelle", 'td');
    }

    public function getNumericableDNS() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        $dns = $this->extractValue($result['body'], "Vos DNS", 'td');
        if ($dns != "") {
            $dns = trim($dns);
            $pos = strpos($dns, "et");
            $dns1 = substr($dns, 0, $pos - 1);
            $dns2 = substr($dns, $pos + 3, strlen($dns) - $pos - 3);
            return array("primary" => $dns1, "secondary" => $dns2);
        }
        return FALSE;
    }

    public function getBandwidth() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        $max = $this->extractValue($result['body'], "Descendant maximum", 'td');
        if ($max == FALSE)
            return FALSE;
        $min = $this->extractValue($result['body'], "Montant maximum", 'td');
        if ($min == FALSE)
            return FALSE;
        $max = substr($max, 0, strlen($max) - 4);
        $min = substr($min, 0, strlen($min) - 4);
        return array('min' => $min, 'max' => $max);
    }

    public function getHWVer() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        return $this->extractValue($result['body'], "Version matériel", 'td');
    }

    public function getSWVer() {
        if (($result = $this->fetch()) == FALSE)
            return FALSE;
        return $this->extractValue($result['body'], "Version logiciel", 'td');
    }

    public function resetModem($login, $password) {
        log::add('NBLabox', 'debug', __METHOD__ . ' reset modem initiated');
        $this->doLogin($login, $password);
          if ($this->isloggeddIn == 0) {
          log::add('NBLabox', 'error', 'cannot login using credential '.$this->login);  return FALSE; }
          else
            $result = $this->restartModem();
        return TRUE;
    }

}

class httpRequest {

    public static function getData($v) {
        if (is_array($_GET))
            if (array_key_exists($v, $_GET))
                return $_GET[$v];
        return FALSE;
    }

}

class NBLabox extends eqLogic {
    /*     * *************************Attributs****************************** */

    public static $_widgetPossibility = array('custom' => true);

    /*     * ***********************Methode static*************************** */

     /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDayly() {

      }
     */
    public static function cronDayly() {
        foreach (self::byType('NBLabox') as $neurall) {
            if ($neurall->getIsEnable() == 1) 
            if ($neurall->getConfiguration('laboxAddr') != '') {
                log::add('NBLabox', 'debug', 'Pull CronDayly pour neurall api');
                $neurall->updateInfo();
                $neurall->toHtml('dashboard');
                $neurall->toHtml('mobile');
                $neurall->refreshWidget();
            }
            }        
    }
    
    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */
    public static function cronHourly() {
        foreach (self::byType('NBLabox') as $neurall) {
            if ($neurall->getIsEnable() == 1) 
            if ($neurall->getConfiguration('laboxAddr') != '') {
                log::add('NBLabox', 'debug', 'Pull CronHourly pour neurall api');
                $neurall->updateInfo();
                $neurall->toHtml('dashboard');
                $neurall->toHtml('mobile');
                $neurall->refreshWidget();
            }
            }
    }


    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
     */
    public static function cron($_eqlogic_id = null) {
//        if ($_eqlogic_id !== null) {
//            $eqLogics = array(eqLogic::byId($_eqlogic_id));
//        } else {
//            $eqLogics = eqLogic::byType('NBLabox');
//        }
//        foreach ($eqLogics as $t) {
//            if ($t->getIsEnable() == 1) {
//            if ($t->getConfiguration('laboxAddr') != '') {
//                log::add('NBLabox', 'debug', 'Pull Cron pour neurall api');
//                $t->updateInfo();
//                $t->toHtml('dashboard');
//                $t->toHtml('mobile');
//                $t->refreshWidget();
//            }
//            }
//        }
        return;
    }

       public function resetModem() {
        try {
            log::add('NBLabox', 'debug', __METHOD__ . "reset modem started");
            $labox = $this->getConfiguration('laboxAddr');
            log::add('NBLabox', 'debug', __METHOD__ . "  addr found is [" . $labox . ']');
            if ($labox == "")
                return;         
            $url = 'http://' . $labox;
//            $url = 'http://192.168.56.1/LaBox/exemples/main.html';
            log::add('NBLabox', 'debug', __METHOD__ . "  query [" . $url. ']');
            $params = array(
                'url' => $url,
                'host' => '',
                'header' => '',
                'method' => 'GET', // 'POST','HEAD'
                'referer' => '',
                'cookie' => '',
                'cookiejar' => '',
                'port' => 80,
                'post_fields' => '', // 'var1=value&var2=value
                'timeout' => 20
            );
            $laBox = new LaBox($params);
            log::add('NBLabox', 'info', __METHOD__ . ' avant reboot tente detection');
            $detect = $laBox->autoDetect();
            if ($detect != FALSE) {
               log::add('NBLabox', 'info', __METHOD__ . ' détecté');
               //$laBox->doLogout(); // supprime les connexions éventuelles
                $feedbackCmd = $this->getCmd(null, 'reboot');
                $login = $this->getConfiguration('laboxLogin');
                $password = $this->getConfiguration('laboxPassword');
                $result = $laBox->resetModem($login, $password);
                if ($result) $laBox->doLogout();
                $feedbackCmd->event($result);
            }
            
           log::add('NBLabox', 'debug', __METHOD__ . ' feedback done '.  json_encode($result, true));
        } catch (Exception $e) {
            log::add('NBLabox', 'debug', __METHOD__ . " update info failed " . $e->getMessage());
            return '';
        }
        return;
    }
    
    public function updateInfo() {
        try {
            log::add('NBLabox', 'debug', __METHOD__ . " get status called");
            $labox = $this->getConfiguration('laboxAddr');
            log::add('NBLabox', 'debug', __METHOD__ . "  addr found is [" . $labox . ']');

            if ($labox == "")
                return; 
            $url = 'http://' . $labox;
//            $url = 'http://192.168.56.1/LaBox/exemples/main.html';
            log::add('NBLabox', 'debug', __METHOD__ . "  query [" . $url. ']');
            $params = array(
                'url' => $url,
                'host' => '',
                'header' => '',
                'method' => 'GET', // 'POST','HEAD'
                'referer' => '',
                'cookie' => '',
                'port' => 80,
                'post_fields' => '', // 'var1=value&var2=value
                'timeout' => 20
            );
            $laBox = new LaBox($params);
            $detect = $laBox->autoDetect();

            if ($detect == FALSE) 
                $feedback = "ko";
            else {
                $feedback = "normal";
                $feedbackCmd1 = $this->getCmd(null, 'laboxip');
                $feedbackCmd1->event(($currentip= $laBox->getPublicIPAddress()));  // enregistre l'adresse ip courante pour la comparer à la précédente
                
                $feedbackCmd2 = $this->getCmd(null, 'laboxgw');
                $feedbackCmd2->event($laBox->getDefaultGateway());
                
                $feedbackCmd3 = $this->getCmd(null, 'laboxhwver');
                $feedbackCmd3->event($laBox->getHWVer());
                
                $feedbackCmd4 = $this->getCmd(null, 'laboxswver');
                $feedbackCmd4->event($laBox->getSWVer());
                
                $feedbackCmd5 = $this->getCmd(null, 'laboxmask');
                $feedbackCmd5->event($laBox->getMask());

                $dns = $laBox->getNumericableDNS();
                $feedbackCmd6 = $this->getCmd(null, 'laboxdns1');
                $feedbackCmd6->event($dns['primary']);
                $feedbackCmd7 = $this->getCmd(null, 'laboxdns2');
                $feedbackCmd7->event($dns['secondary']);

                $dns = $laBox->getBandwidth();
                $feedbackCmd8 = $this->getCmd(null, 'laboxdownload');
                $feedbackCmd8->event($dns['max']);
                $feedbackCmd9 = $this->getCmd(null, 'laboxupload');
                $feedbackCmd9->event($dns['min']);

                $feedbackCmd10 = $this->getCmd(null, 'laboxprevip');
                $previp = $feedbackCmd10->execCmd(); // récupère l'adresse ip précédente
                $feedbackCmd10->event($currentip); // met à jour a valeur avec celle actuelle
                
                if( $previp != $currentip) { // si l'adresse ip a changé, il faut alerter l'utilisateur et régler le DDNS éventuellement
                   log::add('NBLabox', 'info',"l'adresse ip publique de la box a changé ($currentip)");       
                
                }
            }
            $feedbackCmd = $this->getCmd(null, 'laboxetat');
            $feedbackCmd->event($feedback);
           log::add('NBLabox', 'debug', __METHOD__ . " feedback from box " . json_encode($feedback));
        } catch (Exception $e) {
            log::add('NBLabox', 'debug', __METHOD__ . " update info failed " . $e->getMessage());
            return '';
        }
        return;
    }


   /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
    }

    private function createCmd($cmdid, $cmdlabel, $visible = 1) {
        $config = $this->getCmd(null, $cmdid);
        if (!is_object($config)) {
            log::add('NBLabox', 'debug', __METHOD__ . " creation de ".$cmdid);
            $config = new NBLaboxCmd();
            $config->setLogicalId($cmdid);
            $config->setIsVisible($visible);
            $config->setIsHistorized(0);
            $config->setName($cmdlabel);
            $config->setType('info');
            $config->setSubType('string');
            $config->setEventOnly(1);
            $config->setEqLogic_id($this->getId());
            // 05/09/2016 ajout compatibilité avec appli mobile
            $config->setDisplay('generic_type','GENERIC').
            $config->save();
        } else
            log::add('NBLabox', 'debug', __METHOD__ . " ".$cmdid." already exists");    
    }
    
    public function postInsert() {

        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
        
        $this->createCmd('laboxetat', 'Etat Labox');
        $this->createCmd('laboxip', 'IP Labox');
        $this->createCmd('laboxmask', 'Masque Labox');
        $this->createCmd('laboxgw', 'Passerelle Labox');
        $this->createCmd('laboxhwver', 'Version HW Labox');
        $this->createCmd('laboxswver', 'Version SW Labox');
        $this->createCmd('laboxprevip', 'IP publique precedente',0);
        $this->createCmd('laboxdns1', 'DNS primaire');
        $this->createCmd('laboxdns2', 'DNS secondaire');
        $this->createCmd('laboxdownload', 'Debit');
        $this->createCmd('laboxupload', 'Debit montant');
        
        $refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh)) {
            $refresh = new NBLaboxCmd();
            $refresh->setName(__('Rafraichir', __FILE__));
        }
// bouton refresh 
        $refresh->setEqLogic_id($this->getId());
        $refresh->setLogicalId('refresh');
        $refresh->setType('action');
        $refresh->setSubType('other');
        // 05/09/2016 ajout compatibilité avec appli mobile
        $refresh->setDisplay('generic_type','DONT').
        $refresh->setOrder(98);
        $refresh->save();
// bouton reboot
        $reboot = $this->getCmd(null, 'reboot');
        if (!is_object($reboot)) {
            $reboot = new NBLaboxCmd();
            $reboot->setName(__('Redemarrer', __FILE__));
        }
        $reboot->setEqLogic_id($this->getId());
        $reboot->setLogicalId('reboot');
        $reboot->setType('action');
        $reboot->setSubType('other');
        // 05/09/2016 ajout compatibilité avec appli mobile
        $reboot->setDisplay('generic_type','DONT').
        $reboot->setOrder(99);
        $reboot->save();
// display mode
        $display= $this->getCmd('info', 'isDisplay');
        if (!is_object($display)) {
            $display = new NBLaboxCmd();
            $display->setName(__('isDisplay', __FILE__));
        }
        $display->setEqLogic_id($this->getId());
        $display->setLogicalId('isDisplay');
        $display->setType('info');
        $display->setSubType('string');
        // 05/09/2016 ajout compatibilité avec appli mobile
        $display->setDisplay('generic_type','DONT').
        $display->setOrder(97);
        $display->save();
        }

    public function preSave() {
     }

    public function postSave() {
        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
        if (!$this->getId()) // un appel sans ID sort immédiatement sans mise à jour
            return;
        $this->updateInfo();
        // return; 
        $this->toHtml('dashboard');
        $this->toHtml('mobile');
        $this->refreshWidget();
        $display= $this->getCmd('info', 'isDisplay');
        $displayFlag = $this->getConfiguration("isDisplay", "1");
        log::add('NBLabox', 'debug', __METHOD__ . " display flag " . $displayFlag);
        $display->setValue($displayFlag);
        $display->save();
        $display->event($displayFlag);
        log::add('NBLabox', 'debug', __METHOD__ . " refresh widget done " . $this->getName());
    }

    public function preUpdate() {
        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
        if ($this->getConfiguration('laboxAddr') == '')
            throw new Exception(__("L'adresse de la box est vide, entrez l'adresse IP de la box", __FILE__));
        if ($this->getConfiguration('laboxLogin') == '')
            throw new Exception(__("Le nom de connexion est vide, entrez le compte d'administration de la box", __FILE__));
        if ($this->getConfiguration('laboxPassword') == '')
            throw new Exception(__("Le mot de passe est vide, entrez le mot de passe du compte d'administration de la box", __FILE__));
    }

    public function postUpdate() {
        log::add('NBLabox', 'debug', __METHOD__ . " enter postupdate " . $this->getName());
    }

    public function preRemove() {
        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
    }

    public function postRemove() {
        log::add('NBLabox', 'debug', __METHOD__ . " equipment name=" . $this->getName() . " laboxAddr=" . $this->getConfiguration('laboxAddr')
                . " laboxLogin=" . $this->getConfiguration('laboxLogin')
                . " laboxPassword=" . $this->getConfiguration('laboxPassword'));
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin */

    public function toHtml($_version = 'dashboard') {
        log::add('NBLabox', 'debug', __METHOD__ . " update widget code for " . $_version);
        $replace = array(
            '#name#' => $this->getName(),
//            '#id#' => $this->getId(),
            '#background_color#' => $this->getBackgroundColor(jeedom::versionAlias($_version)),
            '#eqLink#' => $this->getLinkToConfiguration(),
            '#laboxAddr#' => $this->getConfiguration('laboxAddr'),
            '#laboxIP#' => $this->getConfiguration('laboxIP')
        );
        log::add('NBLabox', 'debug', __METHOD__ . " update widget code replace initialized");
        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'info') {
                $value = $cmd->execCmd();
            log::add('NBLabox', 'debug', __METHOD__ . " iterate for id=".$cmd->getLogicalId()." name=".$cmd->getName()." val=".$value);
                $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
                $replace['#' . $cmd->getLogicalId() . '#'] = $value;
                $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
                $replace['#' . $cmd->getLogicalId() . '_collectDate#'] = $cmd->getCollectDate();
                if ($cmd->getIsHistorized() == 1) {
                    $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
                }
            } else {
                $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
            }
        }
        $refresh = $this->getCmd(null, 'refresh');
        if (is_object($refresh)) {
            log::add('NBLabox', 'debug', __METHOD__ . " update widget code refresh id=".$refresh->getId());
            $replace['#refresh_id#'] = $refresh->getId();
            $replace['#uid#'] = $refresh->getId();
        }
        $html = template_replace($replace, getTemplate('core', $_version, 'eqlogic', 'NBLabox'));
        log::add('NBLabox', 'debug', __METHOD__ . " update widget code return html");
        return $html;
    }

    /*     * **********************Getteur Setteur*************************** */
}

class NBLaboxCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
        $cmd = $this->getLogicalId() ;
        log::add('NBLabox', 'debug', __METHOD__ . " entered, running cmd=".$cmd);
        switch ($cmd) {
            case "refresh":
                $eqLogic = $this->getEqLogic();
                $eqLogic->updateInfo();
                $eqLogic->toHtml('dashboard');
                $eqLogic->toHtml('mobile');
                $eqLogic->refreshWidget();
                break;
            case 'reboot':
                $eqLogic = $this->getEqLogic();
                $eqLogic->resetModem();
                break;
  
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>
