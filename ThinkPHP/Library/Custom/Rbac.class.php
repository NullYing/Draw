<?php
/**
 * RBAC基于角色权限管理的权限系统
 * @author HyperQing <469379004@qq.com>
 */
namespace Custom;
use Think\Db;
class Rbac {
  /**
   * 检查指定用户和指定权限的授权情况
   * 查询路线：用户-》角色 -> 权限
   * @access public
   * @param string $user 用户名，这里指邮箱
   * @param string $private 权限名
   * @return boolean true,有权限;false,无权限
   */
  public function checkAccess($userid,$private){
    // 查找用户对应的角色
    //用户角色表
    $User_n_role=D('Userrole');
    $role=$User_n_role->where('UserId=%d',$userid)->find();
    // 已经得知用户的角色
    $roleid=intval($role['roleid']);
    $User_n_role=null;

    // 查询目标权限的id
    //权限表
    $Private=D('Private');
    $priv=$Private->where('PrivateName="%s"',$private)->find();
    // 已经得知目标权限的id
    $privateid=intval($priv['privateid']);
    $Private=null;

    // 查询角色是否拥有第id权限
    // 角色权限表
    $Role_n_private=D('Rolepriv');
    $result=$Role_n_private->where('RoleId = %d and PrivateId=%d',$roleid,$privateid)->find();
    if($result){
      return true;
    } else {
      return false;
    }
  }
}