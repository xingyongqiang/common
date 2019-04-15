<?php
namespace app\admin\controller;

use think\Controller;
use think\Request;
use think\Cache;
use auth\Auth;

//use think\Image;

class Common extends Controller
{
    protected $sys_conf; //系统配置
    protected $_controller; //控制器名
    protected $_action; //方法名
    protected $_module; //分组名
    protected $_user; //管理员信息
    protected $_menu_nav; //导航

    public function _initialize()
    {
        $request = Request::instance();
        $this->_controller = $request->controller();
        $this->_action = $request->action();
        $this->_module = $request->module();
        $this->_user = session('user');

        if ($this->_module == "admin") {
            //初始化登录
            $this->init_admin();
            //初始化配置--检验上传图片的水印等信息
            $this->get_config();
            //检验权限--排除文件上传
            $auth = new Auth();
            $name = $this->_module . '/' . $this->_controller . '/' . $this->_action;//地址字符串
            if (!$auth->check($name, $this->_user['id']) && $this->_action !== 'up_img') {
                $this->error('您没有该操作权限！');
            }
            //后台栏目
            $group = $auth->getGroups($this->_user['id']);//用户组信息
            $this->get_menu($group[0]['rules']);
            //模板赋值
            $this->assign([
                'sys_admin' => $this->_user,
                'controller' => strtolower($this->_controller),
                'action' => $this->_action,
                'module' => $this->_module,
                'menu' => $this->_menu_nav,
            ]);
        }
    }

    /**
     * 获取配置信息
     */
    private function get_config()
    {
        $configArr = array();
        $config = db('config')->field('ename,value')->select();
        foreach ($config as $key => $val) {
            $configArr[$val['ename']] = $val['value'];
        }
        $this->sys_conf = $configArr;
    }

    /**
     * 初始化后台登录
     */
    private function init_admin()
    {
        if ($this->_controller == "Login") {
            //防止用户重复登录
            if ($this->_user) {
                //返回登录信息
                $this->error('已经登录，请勿重复登录！', url('Index/index'));
            }
        } else {
            //防止用户未登录
            if (!$this->_user) {
                //返回登录信息
                $this->error('您还未登录，请先登录！', url('Login/index'));
            }
        }
    }

    /**
     * 获取导航
     */
    private function get_menu($rules)
    {
        $uname = $this->_user['uname'];
        $menu = db('user_auth_rule')->field('id,pid,title,name,icon')->where("`status`=1 and pid=0 and `show`=1 and id in ($rules)")->order('sorts asc')->cache($uname)->select();
        $this->_menu_nav = $menu;
    }

    /**
     * 上传缩略图
     */
    public function up_img()
    {
        $info = $this->img_upload('img');
        return $info;
    }

    /**
     * 删除图片
     * 前端ajax传值url，1判断，2清空value，3删除本地图片
     */
    public function del_img()
    {
        if (request()->isAjax()) {
            $img_url = isset($_POST["imgurl"]) ? strval($_POST["imgurl"]) : "";
            $info = $this->del_img_upload($img_url);
            if ($info) {
                ajax_return(1, '删除成功！');
            } else {
                ajax_return(0, '删除失败！');
            }
        }
    }

    /**
     * 图片上传--是否开启缩略图--是否开启水印
     * 配置项缩略图处理-缩略图宽、高、裁剪方式
     * 配置项水印处理-水印位置、类型(文字，图片)、透明度(文字，图片)、大小(文字)、颜色(文字
     */
    private function img_upload($fileImage)
    {
        $file = request()->file($fileImage);
        $info = $file->move(IMG_URL);
        if ($info) {
            //$img_url = IMG_URL . $info->getSaveName();
            //$image = \think\Image::open($img_url);
            //$image->thumb(240, 160, 1)->save($img_url);
            //$image->water('./logo.png',5,50)->save($img_url);
            //$image->text('十年磨一剑 - 为API开发设计的高性能框架', 'webfont.ttf', 20, '#ffffff')->save($img_url);
            return $info->getSaveName();
        } else {
            // 上传失败获取错误信息
            return "";
        }
    }

    /**
     * 撤销上传
     *
     */
    protected function del_img_upload($imgUrl)
    {
        $url = IMG_URL . $imgUrl;
        if (file_exists($url)) {
            $info = @unlink($url);
            if ($info) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 方式一
     * 栏目TO树
     * 把返回的数据集转换成Tree
     */
    protected function cate_tree($cateRes, $pid = 0, $level = 0)
    {
        static $arr = array();
        foreach ($cateRes as $k => $v) {
            if ($v['pid'] == $pid) {
                $v['level'] = $level;
                $arr[] = $v;
                $this->cate_tree($cateRes, $v['id'], $level + 1);
            }
        }
        return $arr;
    }

    /**
     * 清理缓存
     */
    public function cache_clear()
    {
        Cache::clear();
        $this->success('缓存清除成功！');
    }
    
    //获取日期
    function get_week($time, $format = "Y-m-d")
    {
        $week = date('w', $time);
        $weekname = array(1, 2, 3, 4, 5, 6, 7);
        if (empty($week)) {
            $week = 7;
        }
        for ($i = 0; $i <= 6; $i++) {
            $data[$i]['date'] = date($format, strtotime('+' . $i + 1 - $week . ' days', $time));
            $data[$i]['week'] = $weekname[$i];
        }
        return $data;
    }

}
