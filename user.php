<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* Ucenter接口通知处理控制器
* @author yikai.shao<807862588@qq.com>
* 
* @link https://github.com/CodeIgniter-Chinese/ucenter-how-to/blob/master/README.md 教程
* 对ci程序和discuz通过ucenter整合在一起做了清楚的介绍，至于ci自带用户表的情况没有介绍。
*
* 该代码在此基础上，参考ucenter手册中的示例代码，编写了login，register，logout方法，
* 以举例说明如何整合ci自带用户表的情况，不当之处，欢迎指正。
*
* 用户表样例
* CREATE TABLE `example_members` (
*   `uid` int(11) NOT NULL COMMENT 'UID',
*   `username` char(15) default NULL COMMENT '用户名',
*   `admin` tinyint(1) default NULL COMMENT '是否为管理员',
*   PRIMARY KEY  (`uid`)
* ) ENGINE=MyISAM;
*
* 说明
* 
* 登录：http://ci.connect.uc/ci/index.php/user/login
* 注册：http://ci.connect.uc/ci/index.php/user/register
* 注销：http://ci.connect.uc/ci/index.php/user/logout
* 
* 一、需要先建立好数据表
* 二、配置好config/database.php中的相关选项
*/

class User extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->database(); 
		$this->load->helper('url'); // redirect()使用

		include APPPATH.'config/ucenter.php';
        include './uc_client/client.php'; // 注意路径
		
	}

	public function index()
	{
		redirect('user/login');
	}

    public function login()
    {
        $user_info = $this->session->userdata('user');
        if(!empty($user_info['username']))
        {
            exit($user_info['username'].' You are logged in, <a href="logout">Logout</a>');
        }
        if(empty($_POST['submit'])) 
        {
            //登录表单
            echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
            echo 'login:';
            echo '<dl><dt>Username</dt><dd><input name="username"></dd>';
            echo '<dt>Password</dt><dd><input name="password" type="password"></dd></dl>';
            echo '<input name="submit" type="submit"> ';
            echo '</form>';exit;
        } 
        else 
        {
            //通过接口判断登录帐号的正确性，返回值为数组
            list($uid, $username, $password, $email) = uc_user_login($_POST['username'], $_POST['password']);

            $this->session->sess_destroy();
            if($uid > 0) 
            {
                $sql = 'SELECT count(*) FROM example_members WHERE uid="?"';
                $query = $this->db->query($sql, $uid);

                if(!$query->num_rows()) 
                {
                    //判断用户是否存在于用户表，不存在则跳转到激活页面
                    $auth = rawurlencode(uc_authcode("$username\t".time(), 'ENCODE'));
                    echo 'You need to activate the account, to access this application<br><a href="register?action=activation&auth='.$auth.'">继续</a>';
                    exit;
                }
           
                $this->session->set_userdata('user',array(
                        'username' => uc_authcode($uid."\t".$username, 'ENCODE'),
                    ));
                //生成同步登录的代码
                $ucsynlogin = uc_user_synlogin($uid);
                echo 'Login successfully!'.$ucsynlogin.'<br><a href="login">continue</a>';
                exit;
            } 
            elseif($uid == -1) 
            {
                echo 'user not exists';
            } 
            elseif($uid == -2) 
            {
                echo 'password error';
            } 
            else 
            {
                echo 'undefined';
            }
        }
    }

    public function register()
    {
        if(empty($_POST['submit'])) 
        {
            //注册表单
            echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';

            if($_GET['action'] == 'activation') 
            {
                echo 'activate:';
                list($activeuser) = explode("\t", uc_authcode($_GET['auth'], 'DECODE'));
                echo '<input type="hidden" name="activation" value="'.$activeuser.'">';
                echo '<dl><dt>Username</dt><dd>'.$activeuser.'</dd></dl>';
            } 
            else 
            {
                echo 'Register:';
                echo '<dl><dt>Username</dt><dd><input name="username"></dd>';
                echo '<dt>Password</dt><dd><input name="password"></dd>';
                echo '<dt>Email</dt><dd><input name="email"></dd></dl>';
            }
            echo '<input name="submit" type="submit">';
            echo '</form>';
        } 
        else 
        {
            //在UCenter注册用户信息
            $username = '';
            if(!empty($_POST['activation']) && ($activeuser = uc_get_user($_POST['activation']))) 
            {
                list($uid, $username) = $activeuser;
            } 
            else 
            {
                $sql = "SELECT uid FROM example_members WHERE username='$_POST[username]'";
                $query = $this->db->query($sql);
                $res = $query->row();
         
                if(uc_get_user($_POST['username']) && !$res->uid) 
                {
                    //判断需要注册的用户如果是需要激活的用户，则需跳转到登录页面验证
                    echo 'The user does not need to register, please activate the user<br><a href="'.$_SERVER['PHP_SELF'].'">continue</a>';
                    exit;
                }

                $uid = uc_user_register($_POST['username'], $_POST['password'], $_POST['email']);
                if($uid <= 0) 
                {
                    if($uid == -1) 
                    {
                        echo 'The username is invalid';
                    } 
                    elseif($uid == -2) 
                    {
                        echo 'Contains words that is not allowed to register';
                    } 
                    elseif($uid == -3) 
                    {
                        echo 'Username Already exists';
                    } 
                    elseif($uid == -4) 
                    {
                        echo 'Email format is incorrect';
                    } 
                    elseif($uid == -5) 
                    {
                        echo 'This email is not allowed to register';
                    } 
                    elseif($uid == -6) 
                    {
                        echo 'Email has been registered';
                    } 
                    else 
                    {
                        echo 'undefined';
                    }
                } 
                else 
                {
                    $username = $_POST['username'];
                }
            }
            if($username) 
            {
                $data = array(
                        'uid' => $uid,
                        'username' => $username,
                        'admin' => '0',
                    );
                $this->db->insert('example_members', $data);

                //注册成功，设置 Cookie，加密直接用 uc_authcode 函数，用户使用自己的函数
                $this->session->set_userdata('user',array(
                        'username' => uc_authcode($uid."\t".$username, 'ENCODE'),
                    ));
                $ucsynlogin = uc_user_synlogin($uid);
                echo 'Reitster successfully!<br><a href="login">continue</a>'.$ucsynlogin;
                exit;
            }
        }
    }


    public function logout()
    {
    	if(isset($this->session->userdata['user']))
    	{
			$this->session->sess_destroy();
	        //生成同步退出的代码
	        $ucsynlogout = uc_user_synlogout();
	        echo 'Logout successfully!'.$ucsynlogout;
	        exit;
    	}
    	else
    	{
    		redirect('user/login');
    	}
        
    }
}
