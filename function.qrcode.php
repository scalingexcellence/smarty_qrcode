<?php
/**
 * Smarty {qrcode} function plugin
 * 
 * Type:     function<br>
 * Name:     qrcode<br>
 * Date:     Jul 13, 2011<br>
 * Purpose:  Create qrcodes using phpqrcode library<br>
 * Examples: {qrcode value="Hello world!"}
 * Output:   <img src="/images/masthead.gif" width=400 height=23>
 * 
 * @link http://scalingexcellence.co.uk/qrcode_smarty/
 * @author Dimitrios Kouzis-Loukas (dkouzisloukas@scalingexcellence.co.uk) for 
 * @version 1.0
 * @param array $params parameters
 * Input:<br>
 *          - value = the value to create QR code for
 *          - alt = the alt for the image (optional, default empty)
 *          - height = image height (optional, default actual height)
 *          - width = image width (optional, default actual width)
 *          - qrcode_tmp_dir = directory where file should be output (optional, default temp directory of phpqrcode)
 *          - qrcode_tmp_url = prefix for path output (optional, default empty)
 *          - qrcode_libbase = directory where phpqrcode is installed (optional, default inside the smarty plugins directory)
 *          - ecc = Error Correction Level is L, M, Q or H (optional, default L)
 *          - size = Matrix Point Size between 1 and 10 (optional, default 4)
 *          - extra parameters like style, title etc. are forwarded to the img tag
 * $smarty parameters:<br>
 *          - qrcode_tmp_dir = same as above
 *          - qrcode_tmp_url = same as above
 *          - qrcode_libbase = same as above
 *          - qrcode_cache = an QR codes url cache class (see the example of the default FileCache)
 *          - qrcode_uploader = an uploader class (see the example of the default DefaultUploader)
 * @param object &$smarty smarty object
 * @return string 
 * @uses smarty_function_escape_special_chars()
 */

function smarty_function_qrcode($params, &$smarty) {
    require_once(SMARTY_PLUGINS_DIR . 'shared.escape_special_chars.php');


    //A simple built-in cache
    class FileCache {
        function __construct($qrcode_tmp_dir) {
            $this->qrcode_tmp_dir = $qrcode_tmp_dir;
        }

        public function get($val, $ecc, $size) {
            $xfdata = $val.'|'.$ecc.'|'.$size;
            $fdata = $this->qrcode_tmp_dir.'v'.md5($xfdata).$ecc.$size.'.dat';
            
            if (!file_exists($fdata)) {
                return null;
            }
            
            $cached_value = file_get_contents($fdata);
            if (preg_match('/([^,]+),([^,]+),([^,]+),(.*)/',$cached_value,$matches) && $matches[4]==$val) {
                return array($matches[1], $matches[2], $matches[3]);
            }
            return null;
        }
        
        public function set($val, $ecc, $size, $url, $width, $height) {
            $xfdata = $val.'|'.$ecc.'|'.$size;
            $fdata = $this->qrcode_tmp_dir.'v'.md5($xfdata).$ecc.$size.'.dat';
            file_put_contents($fdata, $url.",".$width.",".$height.",".$val);
        }
    }

    //The default uploader doesn't move anything (assuming publicly
    //accessible temp directory);
    class DefaultUploader {
        function __construct($qrcode_tmp_url) {
            $this->qrcode_tmp_url = $qrcode_tmp_url;
        }

        public function upload($filename, $dwidth, $dheight) {
            return array($this->qrcode_tmp_url.basename($filename),$dwidth,$dheight);
        }
    }

    //************************************************************
    
    $keyparams = array_keys($params);
    $smarty_keys = array_keys((array)$smarty->parent);
    
    //Read the value param
    if (!in_array('value', $keyparams) || (($val = trim($params['value']))=='')) {
        trigger_error("assign: missing or empty 'value' parameter", E_USER_NOTICE);
        return;
    }
    
    //Read the (optional) qrcode_tmp_dir
    if (in_array('qrcode_tmp_dir', $keyparams)) {
        $qrcode_tmp_dir = $params['qrcode_tmp_dir'];
    }
    else if (in_array("qrcode_tmp_dir", $smarty_keys)) {
        $qrcode_tmp_dir = $smarty->qrcode_tmp_dir;
    }
    else {
        //set the default value in the temp subdirectory of phpqrcode library (should be writable)
        if (in_array('qrcode_libbase', $keyparams)) {
            $qrcode_tmp_dir = $params['qrcode_libbase'].DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
        }
        else if (in_array("qrcode_libbase", $smarty_keys) && $smarty->qrcode_libbase) {
            $qrcode_tmp_dir = $smarty->qrcode_libbase.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
        }
        else {
            $qrcode_tmp_dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'phpqrcode'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;
        }
    }
    
    //Optional alt parameter (empty by default for xhtml compliance)
    $alt='alt=""';
    if (in_array('alt', $keyparams)) {
        $alt='alt="'.$params['alt'].'" ';
    }
    
    //Optional ecc parameter
    $ecc = 'L';
    if (in_array('ecc', $keyparams)) {
        $ecc = $params['ecc'];
        if (!in_array($ecc, array('L','M','Q','H'))) {
            trigger_error("assign: parameter 'ecc' should be one of L(default),M,Q and H", E_USER_NOTICE);
            return;
        }
    }
    
    //Optional size parameter
    $size = 4;
    if (in_array('size', $keyparams)) {
        $size = (int)$params['size'];
        if ($size > 10 || $size <1) {
            trigger_error("assign: parameter 'size' should be between 1 and 10 (default 4)", E_USER_NOTICE);
            return;
        }
    }

    //Make the extra parameters, img attributes
    $reserved = array('value','alt','qrcode_tmp_dir','qrcode_tmp_url','qrcode_libbase','ecc','size','width','height');
    $extra="";
    foreach($params as $_key => $_val) {
        if (!in_array(strtolower($_key), $reserved) && !is_array($_val)) {
            $extra .= ' ' . $_key . '="' . smarty_function_escape_special_chars($_val) . '"';
        }
    }
    
    //Retrieve the image cache (if not set use default)
    if (in_array("qrcode_cache", $smarty_keys) && $smarty->qrcode_cache) {
        $cache = $smarty->qrcode_cache;
    }
    else {
        $cache = new FileCache($qrcode_tmp_dir);
    }
    
    if ($vals = $cache->get($val, $ecc, $size)) {
        //In the cache
        list($url, $dwidth, $dheight) = $vals;
    }
    else {
        //If not in the cache, create
        $md5xfdata = md5($val.'|'.$ecc.'|'.$size);
        $pure_file = 'v'.$md5xfdata.'.png';
        $filename = $qrcode_tmp_dir . $pure_file;
        
        //Include the qrlib. Don't forget to configure it by editing qrconfig.php
        if (in_array('qrcode_libbase', $keyparams)) {
            require_once $params['qrcode_libbase'].DIRECTORY_SEPARATOR.'qrlib.php';
        }
        else if (in_array("qrcode_libbase", $smarty_keys)) {
            if ($smarty->qrcode_libbase) {
                require_once $smarty->qrcode_libbase.DIRECTORY_SEPARATOR.'qrlib.php';
            }
        }
        else {
            //Include the qrlib. Don't forget to configure it on phpqrcode/qrconfig.php
            require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'phpqrcode'.DIRECTORY_SEPARATOR.'qrlib.php';
        }
        
        //Create output directory if it doesn't exist
        if (!file_exists($qrcode_tmp_dir)) {
            mkdir($qrcode_tmp_dir);
        }
        
        //Create the image
        QRcode::png($val, $filename, $ecc, $size, 2);
        
        //Find out the size of the image
        $v = getimagesize($filename);
        $dwidth = $v[0];
        $dheight = $v[1];
        
        //Upload and post-process
        if (in_array("qrcode_uploader", $smarty_keys) && $smarty->qrcode_uploader) {
            $uploader = $smarty->qrcode_uploader;
        }
        else {
           //Read the qrcode_tmp_url
            if (in_array('qrcode_tmp_url', $keyparams)) {
                $uploader = new DefaultUploader($params['qrcode_tmp_url']);
            }
            else if (in_array("qrcode_tmp_url", $smarty_keys)) {
                $uploader = new DefaultUploader($smarty->qrcode_tmp_url);
            }
            else {
                trigger_error("assign: missing 'qrcode_tmp_url' parameter and no default '\$smarty->qrcode_tmp_url' set", E_USER_NOTICE);
                return;
            }
        }
        
        //Upload the file e.g. to CDN
        list($url,$dwidth,$dheight) = $uploader->upload($filename, $dwidth, $dheight);
            
        //write cache tag file
        $cache->set($val, $ecc, $size, $url, $dwidth, $dheight);
    }
    
    //Pass-through ptional width and height parameters
    if (in_array('width', $keyparams)) {
        $dwidth=$params['width'];
    }
    if (in_array('height', $keyparams)) {
        $dwidth=$params['height'];
    }
    
    //The image tag
    return '<img src="'.$url.'" '.$alt.' width="'.$dwidth.'" height="'.$dheight.'"' . $extra . ' />';
}
