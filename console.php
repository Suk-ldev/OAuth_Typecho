<?php
define('__PLUGIN_ROOT__', __DIR__);
include_once 'common.php';
include 'header.php';
include 'menu.php';
?>
<style>
    svg{
        margin-bottom: -5px;
    }
    .icon{
        width:25px;
        height:25px;
    }
</style>
<div style="text-align:center;">
<?php
    $db = Typecho_Db::get();
    Typecho_Widget::widget('Widget_User')->to($user);
    $iconfile = __PLUGIN_ROOT__."/icon.json";
    if (!file_exists($iconfile)) {
        exit('图标文件不存在，请检查插件目录是否有icon.json文件');
    }
    $str = file_get_contents($iconfile);
    $site = json_decode($str,true);
    $plugin = Typecho_Widget::widget('Widget_Options')->plugin('OAuth');
    $arr = [];
    for ($i = 0; $i < count($site); $i++) {
        $c = $site[$i]['site'];
        if($plugin->$c){
            $arr[] = $site[$i];
        }
    }
    if(isset($_GET['add']) && $_GET['add']){
        $a = $_GET['add'];
        if($plugin->$a){
            if($a === 'casdoor'){
                $casdoor_endpoint = rtrim($plugin->casdoor_endpoint, '/');
                $client_id = $plugin->casdoor_client_id;
                $redirect_uri = Typecho_Common::url('user/bangding', Helper::options()->index);
                $response_type = 'code';
                $scope = 'openid profile email';
                $state = 'casdoor_' . md5(uniqid(rand(), true));
                
                $casdoor_url = $casdoor_endpoint."/login/oauth/authorize?" .
                    "client_id=".$client_id."&" .
                    "response_type=".$response_type."&" .
                    "redirect_uri=".urlencode($redirect_uri)."&" .
                    "scope=".$scope."&" .
                    "state=".$state;
                
                header('Location: ' . $casdoor_url);
                exit;
            }else{
                throw new Typecho_Exception(_t('不支持的第三方登录方式'));
            }
        }else{
            throw new Typecho_Exception(_t('未开通此第三方登陆'));
        }
    }
    
    // 处理清理请求
    if(isset($_GET['clean']) && $_GET['clean']){
        $cleanUid = $_GET['clean'];
        if($cleanUid){
            try {
                $db->query($db->delete('table.oauth')->where('uid = ?', $cleanUid));
                echo '<script>alert("清理成功");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
            } catch (Exception $e) {
                echo '<script>alert("清理失败：'.$e->getMessage().'");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
            }
        }else{
            echo '<script>alert("用户ID不能为空");window.location.href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php";</script>';
        }
        exit;
    }
    $data = [];
    for ($i = 0; $i < count($arr); $i++) {
        if($res = $db->fetchAll($db->select('app','uid','openid','time')->from('table.oauth')->where('app = ?', $arr[$i]['site'])->where('uid = ?', $user->uid))){
            $data[$arr[$i]['site']] = $res;
        }else{
            $data[$arr[$i]['site']] = 0;
        }
    }
    
    // 查找 Typecho 中已删除但 oauth 表中还在的用户
    $allOAuthUsers = $db->fetchAll($db->select('uid')->from('table.oauth')->group('uid'));
    $orphanUsers = [];
    foreach ($allOAuthUsers as $oauthUser) {
        $uid = $oauthUser['uid'];
        $checkUser = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));
        if(!$checkUser){
            $orphanUsers[] = $uid;
        }
    }
?>
</div>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>第三方登录设置</h2>
        </div>
        <div class="container typecho-page-main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-table-wrap">
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="20%">
                            <col width="20%">
                            <col width="20%">
                            <col width="30%">
                            <col width="20%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>站点</th>
                            <th>状态</th>
                            <th>绑定时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($i = 0; $i < count($arr); $i++) {
                                
                            ?>
                            <tr>
                                
                                <td><?php echo $arr[$i]['ico']?></td>
                                <td><?php echo $arr[$i]['name']?>账号</td>
                                <td>
                                    <?php 
                                    if($data[$arr[$i]['site']]){
                                    ?>
                                    <font style="color:green">已绑定</font>
                                    <?php
                                    }else{
                                    ?>
                                    未绑定
                                    <?php
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if($data[$arr[$i]['site']]){
                                        echo date('Y-m-d',$data[$arr[$i]['site']][0]['time']);
                                    }else{
                                    ?>
                                    <?php
                                    }
                                    ?>
                                </td>
                                <td style="font-weight:bold">
                                    <?php 
                                    if($data[$arr[$i]['site']]){
                                        echo '<span style="color:#999;">已绑定</span>';
                                    }else{
                                        echo '<a href="'.$options->adminUrl.'extending.php?panel=OAuth/console.php&add='.$arr[$i]['site'].'">绑定</a>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>清理OAuth数据</h2>
        </div>
        <div class="container typecho-page-main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-table-wrap">
                    <?php if(count($orphanUsers) > 0): ?>
                        <p style="color:red;">发现 <?php echo count($orphanUsers); ?> 个 Typecho 已删除但 OAuth 表中还在的用户：</p>
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="20%">
                                <col width="40%">
                                <col width="40%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>用户ID</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanUsers as $uid): ?>
                                <tr>
                                    <td><?php echo $uid; ?></td>
                                    <td><font style="color:red;">用户已删除</font></td>
                                    <td>
                                        <a href="<?php echo $options->adminUrl; ?>extending.php?panel=OAuth/console.php&clean=<?php echo $uid; ?>" onclick="return confirm('确定要清理用户ID为 <?php echo $uid; ?> 的OAuth数据吗？此操作不可恢复！')" class="btn btn-danger">清理</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color:green;">没有发现孤立数据，所有 OAuth 数据都正常。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include 'footer.php';
?>
