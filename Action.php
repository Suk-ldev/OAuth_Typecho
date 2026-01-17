<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
define('__PLUGIN_ROOT__', __DIR__);
class OAuth_Action extends Typecho_Widget implements Widget_Interface_Do
{
    
    public $dir;
    public $plugin;
    public function __construct()
    {
        $this->dir = Typecho_Widget::widget('Widget_Options')->pluginUrl.'/OAuth';
        $this->plugin = Typecho_Widget::widget('Widget_Options')->plugin('OAuth');
    }
    
    public function action(){
        throw new Typecho_Exception(_t('OAuth登录插件！看到这段文字表示插件已安装'));
        exit();
    }
    
    public function login(){
        $plugin = $this->plugin;
        if(isset($plugin->casdoor) && $plugin->casdoor){
            $casdoor_endpoint = isset($plugin->casdoor_endpoint) ? rtrim($plugin->casdoor_endpoint, '/') : '';
            $client_id = isset($plugin->casdoor_client_id) ? $plugin->casdoor_client_id : '';
            
            if(empty($casdoor_endpoint) || empty($client_id)){
                $this->error('Casdoor配置不完整，请检查插件设置');
            }
            
            $redirect_uri = urlencode(Typecho_Common::url('user/callback', Helper::options()->index));
            $response_type = 'code';
            $scope = 'openid profile email';
            $state = 'casdoor_' . md5(uniqid(rand(), true));
            
            $casdoor_url = $casdoor_endpoint."/login/oauth/authorize?" .
                "client_id=".$client_id."&" .
                "response_type=".$response_type."&" .
                "redirect_uri=".$redirect_uri."&" .
                "scope=".$scope."&" .
                "state=".$state;
                
            header('Location: ' . $casdoor_url);
            exit;
        }else{
            $this->error('Casdoor登录未开启');
        }
    }
    
    public function api(){
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if(empty($action)){
            $this->json([
                'code' => 0,
                'msg' => 'api error: action is empty'
            ]);
            return;
        }
        
        switch ($action) {
            case 'url':
                $site = isset($_POST['site']) ? $_POST['site'] : '';
                $from = isset($_POST['from']) ? $_POST['from'] : '';
                if($site){
                    if($site === 'casdoor'){
                        $callback = Typecho_Common::url('user/callback', Helper::options()->index);
                        
                        $casdoor_endpoint = rtrim($this->plugin->casdoor_endpoint, '/');
                        $client_id = $this->plugin->casdoor_client_id;
                        $redirect_uri = urlencode($callback);
                        $response_type = 'code';
                        $scope = 'openid profile email';
                        $state = 'casdoor_' . md5(uniqid(rand(), true));
                        
                        $casdoor_url = $casdoor_endpoint."/login/oauth/authorize?" .
                            "client_id=".$client_id."&" .
                            "response_type=".$response_type."&" .
                            "redirect_uri=".$redirect_uri."&" .
                            "scope=".$scope."&" .
                            "state=".$state;
                            
                        $this->json([
                            'code' => 1,
                            'url' => $casdoor_url,
                            'height' => 600,
                            'width' => 500
                        ]);
                    }else{
                        $this->json([
                            'code' => 0,
                            'msg' => '不支持的第三方登录方式'
                        ]);
                    }
                }else{
                    $this->json([
                        'code' => 0,
                        'msg' => 'site is empty'
                    ]);
                }
                break;
            default:
                $this->json([
                    'code' => 0,
                    'msg' => 'api error'
                ]);
                break;
        }
    }

    public function bangding(){
        $code = isset($_GET['code']) ? $_GET['code'] : '';
        $state = isset($_GET['state']) ? $_GET['state'] : '';
        
        if($code){
            $db = Typecho_Db::get();
            Typecho_Widget::widget('Widget_User')->to($user);
            Typecho_Widget::widget('Widget_Options')->to($options);
            
            if(isset($state) && strpos($state, 'casdoor_') === 0) {
                $this->handleCasdoorBinding($code, $user, $options);
            } else {
                echo '<script>alert("不支持的第三方登录方式");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
            }
        }else{
            echo '<script>alert("非法访问");window.location.href="/";</script>';
        }
    }


    public function callback(){
        $db = Typecho_Db::get();
        $code = @$_GET['code'];
        $state = @$_GET['state'];
        
        if($code){
            if(isset($_GET['state']) && strpos($_GET['state'], 'casdoor_') === 0) {
                $this->handleCasdoorCallback($code);
            } else {
                $this->error('不支持的第三方登录方式');
            }
        }else {
            $this->error('授权回调代码错误！');
        }
    }

    protected function json($arr){
        header("Content-type:application/json;charset=utf-8");
        print(json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        exit();
    }

    //设置登录
    protected function SetLogin($uid, $expire = 30243600) {
        $db = Typecho_Db::get();
        Typecho_Widget::widget('Widget_User')->simpleLogin($uid);
        $authCode = function_exists('openssl_random_pseudo_bytes') ?
                bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
        Typecho_Cookie::set('__typecho_uid', $uid, time() + $expire);
        Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), time() + $expire);
        //更新最后登录时间以及验证码
        $db->query($db->update('table.users')->expression('logged', 'activated')->rows(array('authCode' => $authCode))->where('uid = ?', $uid));
    }

    private function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    protected function error($msg = ''){
        exit(require __PLUGIN_ROOT__.'/lib/error.php');
    }
    //快捷登录完成界面
    protected function Ok(){
        $from = isset($_SESSION['from']) ? urldecode($_SESSION['from']) : '';
        if(!$from){
            $from = Typecho_Common::url('/', Helper::options()->index);
        }
        print '
<!DOCTYPE html>
<html>
  <head>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>加载中，请稍候…</title>
    <meta name="theme-color" content="#0C4475" />
    <link rel="shortcut icon" href="/favicon.ico">
    <style type="text/css">@charset "UTF-8";html,body{margin:0;padding:0;width:100%;height:100%;background-color:#DB4D6D;display:flex;justify-content:center;align-items:center;font-family:"微軟正黑體";}.monster{width:110px;height:110px;background-color:#E55A54;border-radius:20px;position:relative;display:flex;justify-content:center;align-items:center;flex-direction:column;cursor:pointer;margin:10px;box-shadow:0px 10px 20px rgba(0,0,0,0.2);position:relative;animation:jumping 0.8s infinite alternate;}.monster .eye{width:40%;height:40%;border-radius:50%;background-color:#fff;display:flex;justify-content:center;align-items:center;}.monster .eyeball{width:50%;height:50%;border-radius:50%;background-color:#0C4475;}.monster .mouth{width:32%;height:12px;border-radius:12px;background-color:white;margin-top:15%;}.monster:before,.monster:after{content:"";display:block;width:20%;height:10px;position:absolute;left:50%;top:-10px;background-color:#fff;border-radius:10px;}.monster:before{transform:translateX(-70%) rotate(45deg);}.monster:after{transform:translateX(-30%) rotate(-45deg);}.monster,.monster *{transition:0.5s;}@keyframes jumping{50%{top:0;box-shadow:0px 10px 20px rgba(0,0,0,0.2);}100%{top:-50px;box-shadow:0px 120px 50px rgba(0,0,0,0.2);}}@keyframes eyemove{0%,10%{transform:translate(50%);}90%,100%{transform:translate(-50%);}}.monster .eyeball{animation:eyemove 1.6s infinite alternate;}h2{color:white;font-size:20px;margin:20px 0;}.pageLoading{position:fixed;width:100%;height:100%;left:0;top:0;display:flex;justify-content:center;align-items:center;background-color:#0C4475;flex-direction:column;transition:opacity 0.5s 0.5s;}.loading{width:200px;height:8px;margin-top:0px;border-radius:5px;background-color:#fff;overflow:hidden;transition:0.5s;}.loading .bar{background-color:#E55A54;width:0%;height:100%;}</style>
  </head>
  <body>
    <div class="pageLoading">
      <div class="monster">
        <div class="eye">
          <div class="eyeball"></div>
        </div>
        <div class="mouth"></div>
      </div><h2>页面加载中...</h2>
    </div>
    <script>  
        setTimeout(function(){
            top.location = "'.$from.'";
        }, 1000);
        setTimeout(function(){
            if(window.opener.location.href){
                window.opener.location.reload(true);self.close();
            }else{
                window.location.replace="'.$from.'";
            }
        }, 500);
        setTimeout(function(){window.opener=null;window.close();}, 50000);
    </script> 
  </body>
</html>';
    }
    
    /**
      * 处理Casdoor回调
      */
     protected function handleCasdoorCallback($code) {
         $db = Typecho_Db::get();
         
         // 获取Casdoor配置
         $casdoor_endpoint = rtrim($this->plugin->casdoor_endpoint, '/');
         $client_id = $this->plugin->casdoor_client_id;
         $client_secret = $this->plugin->casdoor_client_secret;
         $redirect_uri = Typecho_Common::url('user/callback', Helper::options()->index);
         
         // 使用授权码获取访问令牌
         $token_url = $casdoor_endpoint . '/api/login/oauth/access_token';
         
         $post_data = array(
             'grant_type' => 'authorization_code',
             'client_id' => $client_id,
             'client_secret' => $client_secret,
             'code' => $code,
             'redirect_uri' => $redirect_uri
         );
         
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $token_url);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
         
         $response = curl_exec($ch);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
         
         if ($http_code === 200) {
             $token_data = json_decode($response, true);
             
             if (isset($token_data['access_token'])) {
                 $access_token = $token_data['access_token'];
                 
                 // 使用访问令牌获取用户信息
                 $user_info_url = $casdoor_endpoint . '/api/userinfo';
                 
                 $ch = curl_init();
                 curl_setopt($ch, CURLOPT_URL, $user_info_url);
                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                     'Authorization: Bearer ' . $access_token,
                     'Content-Type: application/json'
                 ));
                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                 
                 $user_response = curl_exec($ch);
                 $user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                 curl_close($ch);
                 
                 if ($user_http_code === 200) {
                     $user_data = json_decode($user_response, true);
                     
                     if (isset($user_data['sub'])) { // sub是用户唯一标识符
                         $openid = $user_data['sub'];
                         
                         // 检查是否已有用户绑定此Casdoor账户
                         $query = $db->select()->from('table.oauth')->where('openid = ?', $openid)->where('app = ?', 'casdoor'); 
                         $IsUser = $db->fetchAll($query);
                         
                         if (count($IsUser)) {
                             $this->SetLogin($IsUser[0]['uid']);
                             $this->Ok();
                         } else {
                             $nickname = isset($user_data['name']) ? $user_data['name'] : (isset($user_data['preferred_username']) ? $user_data['preferred_username'] : 'user_' . time());
                             $email = isset($user_data['email']) ? $user_data['email'] : '';
                             
                             if(empty($nickname)){
                                 $nickname = 'user_' . time();
                             }
                             
                             if(empty($email)){
                                 $email = 'user_' . time() . '@example.com';
                             }
                             
                             // 检查用户名是否已存在，如果存在则添加后缀
                             $checkUser = $db->fetchRow($db->select()->from('table.users')->where('name = ?', $nickname));
                             if($checkUser){
                                 $nickname = $nickname . '_' . time();
                             }
                             
                             // 检查邮箱是否已存在，如果存在则生成新的
                             $checkEmail = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $email));
                             if($checkEmail){
                                 $email = 'user_' . time() . '@example.com';
                             }
                             
                             $hasher = new PasswordHash(8, true);
                             $random_password = $this->generateRandomPassword();
                             $hashed_password = $hasher->HashPassword($random_password);
                             
                             $data = array(
                                 'name' => $nickname,
                                 'screenName' => $nickname,
                                 'password' => $hashed_password,
                                 'mail' => $email,
                                 'created' => time(),
                                 'group' => 'subscriber'
                             );
                             
                             try {
                                 $insert = $db->query($db->insert('table.users')->rows($data));
                             } catch (Exception $e) {
                                 throw new Typecho_Exception(_t('用户创建失败：') . $e->getMessage());
                             }
                             
                             if(!$insert){
                                 throw new Typecho_Exception(_t('用户创建失败，insert为空'));
                             }
                             
                             $lastInsertId = $insert;
                             if(!$lastInsertId){
                                 throw new Typecho_Exception(_t('无法获取新用户ID，lastInsertId为：') . var_export($lastInsertId, true));
                             }
                             
                             $addGm = array(
                                 'uid'=> $lastInsertId,
                                 'app'=> 'casdoor',
                                 'openid' => $openid,
                                 'time' => time(),
                             );
                             
                             try {
                                 $insertGm = $db->insert('table.oauth')->rows($addGm);
                                 $insertId = $db->query($insertGm);
                             } catch (Exception $e) {
                                 throw new Typecho_Exception(_t('绑定信息插入失败：') . $e->getMessage());
                             }
                             
                             if(!$insertId){
                                 throw new Typecho_Exception(_t('绑定信息插入失败，insertId为空'));
                             }
                             
                             $this->SetLogin($lastInsertId);
                             $this->Ok();
                         }
                     } else {
                         $this->error('获取用户信息失败：' . (isset($user_data['msg']) ? $user_data['msg'] : '未知错误'));
                     }
                 } else {
                     $this->error('获取用户信息失败，HTTP状态码：' . $user_http_code);
                 }
             } else {
                 $this->error('获取访问令牌失败：' . (isset($token_data['error_description']) ? $token_data['error_description'] : '未知错误'));
             }
         } else {
             $this->error('获取访问令牌失败，HTTP状态码：' . $http_code);
         }
     }
     
     /**
      * 处理Casdoor绑定
      */
     protected function handleCasdoorBinding($code, $user, $options) {
         $db = Typecho_Db::get();
         
         // 获取Casdoor配置
         $casdoor_endpoint = rtrim($this->plugin->casdoor_endpoint, '/');
         $client_id = $this->plugin->casdoor_client_id;
         $client_secret = $this->plugin->casdoor_client_secret;
         $redirect_uri = Typecho_Common::url('user/bangding', Helper::options()->index);
         
         // 使用授权码获取访问令牌
         $token_url = $casdoor_endpoint . '/api/login/oauth/access_token';
         
         $post_data = array(
             'grant_type' => 'authorization_code',
             'client_id' => $client_id,
             'client_secret' => $client_secret,
             'code' => $code,
             'redirect_uri' => $redirect_uri
         );
         
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $token_url);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
         
         $response = curl_exec($ch);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
         
         if ($http_code === 200) {
             $token_data = json_decode($response, true);
             
             if (isset($token_data['access_token'])) {
                 $access_token = $token_data['access_token'];
                 
                 // 使用访问令牌获取用户信息
                 $user_info_url = $casdoor_endpoint . '/api/userinfo';
                 
                 $ch = curl_init();
                 curl_setopt($ch, CURLOPT_URL, $user_info_url);
                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                 curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                     'Authorization: Bearer ' . $access_token,
                     'Content-Type: application/json'
                 ));
                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                 
                 $user_response = curl_exec($ch);
                 $user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                 curl_close($ch);
                 
                 if ($user_http_code === 200) {
                     $user_data = json_decode($user_response, true);
                     
                     if (isset($user_data['sub'])) { // sub是用户唯一标识符
                         $openid = $user_data['sub'];
                         
                         // 检查该用户是否已经绑定了 Casdoor
                         $checkUserBinding = $db->fetchRow($db->select()->from('table.oauth')->where('uid = ?', $user->uid)->where('app = ?', 'casdoor'));
                         if($checkUserBinding){
                             echo '<script>alert("您已经绑定了Casdoor账号，不能重复绑定");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                             exit;
                         }
                         
                         // 检查是否已有用户绑定此Casdoor账户
                         $query = $db->select()->from('table.oauth')->where('openid = ?', $openid)->where('app = ?', 'casdoor'); 
                         $IsUser = $db->fetchAll($query);
                         
                         if (count($IsUser)) {
                             echo '<script>alert("该第三方账号已被其它账号绑定");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                         } else {
                             $addGm = array(
                                 'uid'=> $user->uid,
                                 'app'=> 'casdoor',
                                 'openid' => $openid,
                                 'time' => time(),
                             );
                             $insert = $db->insert('table.oauth')->rows($addGm);
                             $insertId = $db->query($insert);
                             if($insertId){
                                 echo '<script>alert("绑定成功");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                             }else{
                                 echo '<script>alert("插件内部错误，请联系开发者");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                             }
                         }
                     } else {
                         echo '<script>alert("获取用户信息失败：' . (isset($user_data['msg']) ? $user_data['msg'] : '未知错误') . '");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                     }
                 } else {
                     echo '<script>alert("获取用户信息失败，HTTP状态码：' . $user_http_code . '");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
                 }
             } else {
                 echo '<script>alert("获取访问令牌失败：' . (isset($token_data['error_description']) ? $token_data['error_description'] : '未知错误') . '");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
             }
         } else {
             echo '<script>alert("获取访问令牌失败，HTTP状态码：' . $http_code . '");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
         }
     }
}
