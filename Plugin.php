<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
define('__PLUGIN_ROOT__', __DIR__);
/**
 * <strong style="color:#000000;">OAuth登录插件</strong>
 * 
 * @package OAuth
 * @author Suk
 * @version 2.0
 * @update: 2023-5-27
 * @link //imsuk.cn
 */
class OAuth_Plugin implements Typecho_Plugin_Interface
{
    public static $panel = 'OAuth/console.php';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Helper::addPanel(1, self::$panel, _t('快捷登录绑定'), _t('账号快捷登录绑定'), 'subscriber');
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'footer');
        Helper::addRoute('OAuth_api', '/user/api', 'OAuth_Action', 'api');
        Helper::addRoute('OAuth_callback', '/user/callback', 'OAuth_Action', 'callback');
        Helper::addRoute('OAuth_bangding', '/user/bangding', 'OAuth_Action', 'bangding');
        Helper::addRoute('OAuth_login', '/user/login', 'OAuth_Action', 'login');
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $sql = "CREATE TABLE `{$prefix}oauth` (
  `id` INT(255) NOT NULL AUTO_INCREMENT,
  `app` TEXT NOT NULL,
  `uid` INT(255) NOT NULL,
  `openid` TEXT NOT NULL,
  `time` TEXT NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;";
            $db->query($sql);
            return '插件安装成功!数据库安装成功';
        } catch (Typecho_Db_Exception $e) {
            if ('42S01' == $e->getCode()) {
                return '插件安装成功!数据库已存在!';
            }
        }
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){
        Helper::removePanel(1, self::$panel);
        Helper::removeRoute('OAuth_api');
        Helper::removeRoute('OAuth_callback');
        Helper::removeRoute('OAuth_bangding');
        Helper::removeRoute('OAuth_login');
        return '插件卸载成功';
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $title = new Typecho_Widget_Helper_Layout('div', array('class=' => 'typecho-page-title'));
        $title->html('<h2>Casdoor配置</h2>');
        $form->addItem($title);
        $casdoor_enabled = new Typecho_Widget_Helper_Form_Element_Radio('casdoor', array('1' => _t('开启'), '0' => _t('关闭')), '0', _t('Casdoor登录'),_t('开启或关闭Casdoor登录功能'));
        $form->addInput($casdoor_enabled);
        $casdoor_endpoint = new Typecho_Widget_Helper_Form_Element_Text('casdoor_endpoint', NULL, NULL, _t('Casdoor服务器地址'), _t('例如: https://door.casdoor.com'));
        $form->addInput($casdoor_endpoint);
        $casdoor_client_id = new Typecho_Widget_Helper_Form_Element_Text('casdoor_client_id', NULL, NULL, _t('Casdoor客户端ID'), _t('在Casdoor应用中创建的客户端ID'));
        $form->addInput($casdoor_client_id);
        $casdoor_client_secret = new Typecho_Widget_Helper_Form_Element_Text('casdoor_client_secret', NULL, NULL, _t('Casdoor客户端密钥'), _t('在Casdoor应用中创建的客户端密钥'));
        $form->addInput($casdoor_client_secret);
        $casdoor_organization = new Typecho_Widget_Helper_Form_Element_Text('casdoor_organization', NULL, NULL, _t('Casdoor组织名称'), _t('Casdoor中的组织名称'));
        $form->addInput($casdoor_organization);
        $casdoor_application = new Typecho_Widget_Helper_Form_Element_Text('casdoor_application', NULL, NULL, _t('Casdoor应用名称'), _t('Casdoor中的应用名称'));
        $form->addInput($casdoor_application);
        $callback_url = Typecho_Common::url('user/callback', Helper::options()->index);
        $callback_info = new Typecho_Widget_Helper_Form_Element_Text('callback_url', NULL, $callback_url, _t('回调地址'), _t('请将此地址添加到Casdoor应用的回调URL列表中'));
        $callback_info->input->setAttribute('readonly', 'readonly');
        $form->addInput($callback_info);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
     
    /**
     *为header添加css文件
     * @return void
     */
    public static function header()
    {
        print <<<HTML
<style>
    .icon{
        margin-top: 5px; 
    }
</style>
HTML;
    }
    
        /**
     *为footer添加js文件
     * @return void
     */
    public static function footer(){
        $api = Typecho_Common::url('user/api', Helper::options()->index);
        if(!Typecho_Widget::widget('Widget_User')->hasLogin()){
        print <<<HTML
<script>
    function GetOauthUrl(site){
        let xhr = new XMLHttpRequest();
        xhr.open('post', '{$api}');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                let res = JSON.parse(xhr.responseText);
                if(res.code == 1){
                    window.location.href = res.url;
                }else{
                    alert(res.msg || '登录失败');
                }
            }
        }
        let from = encodeURIComponent(window.location.href);
        xhr.send('action=url&site='+site+'&from='+from);
    }
    function GetOauthIcon(){
        let obj = '#OauthIcon';
        if(window.OauthIconData){
            let ico = window.OauthIconData;
            let html = '';
            for(let i = 0; i < ico.length; i++){
                html += '<a onclick="GetOauthUrl(\''+ico[i].site+'\')" class="btn btn-rounded btn-sm btn-icon btn-default" data-toggle="tooltip" data-placement="bottom" data-original-title="'+ico[i].name+'账号登陆">'+ico[i].ico+'</a>';
            }
            document.querySelectorAll(obj).forEach(e => {
                e.innerHTML = html;
            });
            console.log('第三方登录按钮加载完成');
            return;
        }
        let xhr = new XMLHttpRequest();
        xhr.open('post', '{$api}');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                let res = JSON.parse(xhr.responseText);
                if(res.code == 1){
                    window.OauthIconData = res.data;
                    let html = '';
                    for(let i = 0; i < res.data.length; i++){
                        html += '<a onclick="GetOauthUrl(\''+res.data[i].site+'\')" class="btn btn-rounded btn-sm btn-icon btn-default" data-toggle="tooltip" data-placement="bottom" data-original-title="'+res.data[i].name+'账号登陆">'+res.data[i].ico+'</a>';
                    }
                    document.querySelectorAll(obj).forEach(e => {
                        e.innerHTML = html;
                    });
                    console.log('第三方登录按钮加载完成');
                }else{
                    console.log('第三方登录按钮加载失败');
                }
            }
        }
        let from = encodeURIComponent(window.location.href);
        xhr.send('action=icon');
    }GetOauthIcon();
</script>
HTML;
        }else{
            print <<<HTML
<script>
    function GetOauthIcon(){
        console.log('已登录');
    }
</script>
HTML;
        }
    }

    public static function oauth(){
        if(!Typecho_Widget::widget('Widget_User')->hasLogin()){
            $plugin = Typecho_Widget::widget('Widget_Options')->plugin('OAuth');
            if($plugin->casdoor){
                $api_url = Typecho_Common::url('user/api', Helper::options()->index);
                print <<<HTML
<div class="row text-center" style="margin-top:10px;">
    <p class="text-muted letterspacing indexWords">Casdoor登录</p>
</div>
<div class="row text-center" style="margin-top:-5px;">
<p id="social-buttons" style="display: flex;margin-top: 8px;justify-content: center;">
    <button type="button" class="btn btn-rounded btn-sm btn-info" onclick="casdoorLogin()"><i class="fa fa-fw fa-key"></i> Casdoor登录</button>
</p>
</div>
<script>
function casdoorLogin() {
    let from = encodeURIComponent(window.location.href);
    fetch('{$api_url}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=url&site=casdoor&from=' + from
    }).then(response => response.json()).then(data => {
        console.log(data);
        if(data.code == 1 && data.url){
            window.location.href = data.url;
        }else{
            alert(data.msg || '登录失败');
        }
    }).catch(error => {
        console.error('Error:', error);
        alert('请求失败');
    });
}
</script>
HTML;
            }
        }
    }

    public static function url($type){
        switch ($type) {
            case 'login':
                return Typecho_Common::url('user/login', Helper::options()->index);
            default:
                return '';
        }
    }
}
