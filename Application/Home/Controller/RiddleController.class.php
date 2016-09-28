<?php
namespace Home\Controller;
use Think\Controller;


class RiddleController extends Controller {
	//通讯密钥
	  const CONNECT_KEY = "XMdrawkey";
	  const QUSTION_SCORE = 1;
	  const TIME_OUT = 60*10;
    const END_QUSTION_NUM = 3;
  
    public function index(){
      $this->_checkReceive($openid,$nickname,$msg);
      //录入手机
  		$SqlContact=D("Contact");
  		$result=$SqlContact->where('openid="%s"',$openid)->count();
  		if($result==0){
        $this->_logPhone($openid,$nickname,$msg);
  		}
      $SqlUser=D("User");
      $result=$SqlUser->where('openid="%s"',$openid)->count();
      if($result==0){
        $user['openid']=$openid;
        $user['status']='willstart';
        $SqlUser->data($user)->add();
      }
      $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
      if(!$UserInfo){
        $this->data='小喵在数据库中找不到您，呜呜呜，请回复“取消”，之后召唤客服';
        $this->display();
        exit;
      }
      //进入状态
      $this->_entern_Status($SqlUser,$UserInfo,$openid,$msg);
    }
  protected function _checkFoul($SqlUser,$UserInfo,$openid,$msg){
    if($UserInfo['foultime']>10){
      $user['openid']=$openid;
      $user['status']='Baned';
      $SqlUser->data($user)->save();
      $this->data='喵！坏人，尝试违规刷题，您已被小喵封印，小喵不准您参加本次活动啦！';
      $this->display();
      exit;
    }
  }
  protected function _checkReceive(&$openid,&$nickname,&$msg){
      if(IS_POST){
        $key=I('post.key',"");
        if($key!=self::CONNECT_KEY){
          \Think\Log::record('非法密钥：'.$key,'WARN');
          $this->data='非法请求';
          $this->display();
          exit;
        }
        $openid=I('post.openid',"");
        if($openid==""){
          \Think\Log::record('非法openid','WARN');
          $this->data='非法请求';
          $this->display();
          exit;
        }
        $nickname=I('post.nickname',"");
        if($nickname==""){
          \Think\Log::record('非法nickname：'.$openid,'WARN');
          $this->data='非法昵称，请修改您的微信昵称，不建议带有表情符号';
          $this->display();
          exit;
        }
        $msg=I('post.msg',"");
        if($msg==""){
          \Think\Log::record('非法msg：'.$openid.$msg,'WARN');
          $this->data='非法请求';
          $this->display();
          exit;
        }
      }
      else{
        $this->data='非法请求';
        \Think\Log::record('非post','WARN');
        $this->display();
        exit;
      }
  }
  protected function _entern_Status($SqlUser,$UserInfo,$openid,$msg){
    $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
    switch ($UserInfo['status']) {
      case 'willstart':
        if($msg=='开始'){
          $this->_startActivity($SqlUser,$UserInfo,$openid,$msg);
        }
        else{
          $this->data='请回复“开始”，开始计时';
          $this->display();
          exit;
        }
        break;
      case 'End':
        if($UserInfo['grade'] >= self::END_QUSTION_NUM){
          $SqlContact=D("Contact");
          $ContactInfo=$SqlContact->where('openid="%s"',$openid)->find();
          $AwardsName=['明信片','流量卡','QQ公仔'];
          $awardstype=$ContactInfo['awardstype'];
          if($awardstype>0 && $awardstype<=3){
            $this->data='本活动只能参与一次，恭喜您抽中了【'.$AwardsName[$awardstype-1].'】，我们将在活动结束后公布获奖名单,并将发送短信通知您领奖\n\n回复“取消”回到正常模式';
          }
          else{
            $this->data='感谢您参与本次活动，很遗憾你没有抽到任何奖品\n\n回复“取消”回到正常模式';
          }
        }
        else{
          $this->data='本活动只能参与一次，很遗憾您没有答对'.(self::END_QUSTION_NUM).'题，无法获得抽奖机会,感谢您的参与\n\n回复“取消”回到正常模式';
        }
        $this->display();
        break;
      case 'Starting':
        //刷题封禁
        $this->_checkFoul($SqlUser,$UserInfo,$openid,$msg);
        //活动计时到达
        $this->_checkTimeOut($SqlUser,$UserInfo,$openid,$msg);
        $this->_judge_Answer($openid,$msg);
        $this->_endActivity($SqlUser,$UserInfo,$openid,$msg);
        $this->_getRiddle($openid);
        break;
      case 'Baned':
        $this->data='喵！坏人，尝试违规刷题，您已被小喵封印，小喵不准您参加本次活动啦！';
        $this->display();
        break;
      default:
        $this->data='呜呜呜，处于无效状态，请回复“取消”，之后召唤客服';
        $this->display();
        break;
      }
      exit;
  }
  protected function _logPhone($openid,$nickname,$msg){
    if(!preg_match("/1[3458]{1}\d{9}$/",$msg)){
      $this->data='请输入正确的手机号';
      $this->display();
      exit;
    }
    $SqlContact=D("Contact");
    $result=$SqlContact->where('phone="%s"',$msg)->count();
    if($result!=0){
      $this->data='喵，该手机号已被使用，请重新输入';
      $this->display();
      exit;
    }
    else{
      $contect['openid']=$openid;
      $contect['nickname']=$nickname;
      $contect['phone']=$msg;
      $contect['jointime']=date('Y-m-d H:i:s');
      $SqlContact->data($contect)->add();
      $this->data='成功录入您的手机号，请回复“开始”，开始计时';
      $this->display();
      exit;
    }
  }
  protected function _startActivity($SqlUser,$UserInfo,$openid,$msg){
      $user['openid']=$openid;
      $user['status']='Starting';
      $user['starttime']=date('Y-m-d H:i:s');
      $SqlUser->data($user)->save();
      $this->_entern_Status($SqlUser,$UserInfo,$openid,$msg);
  }
  protected function _endActivity($SqlUser,$UserInfo,$openid,$msg){
    $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
    if($UserInfo['grade'] >= self::END_QUSTION_NUM){
      $user['openid']=$openid;
      $user['status']='End';
      $user['finalquestion']=NULL;
      $user['firstend']=date('Y-m-d H:i:s');
      $SqlUser->data($user)->save();

      $fail=true;
      //控制中奖几率
      $rank_num = rand(1,100);
      if($rank_num<=15){
        $awardtype=1;
      }
      else if($rank_num<=35){
        $awardtype=2;
      }
      else if($rank_num<=40){
        $awardtype=3;
      }
      if($awardtype <= 3){
         $SqlAwards=D("Awards");
         $SqlAwards->startTrans();

        $ret=false;$flag=false;
        $AwardsInfo=$SqlAwards->where('id="0"')->lock(true)->find();
        if($awardtype==1 && $AwardsInfo['postcard']>0){
            $AwardsInfo['postcard']=$AwardsInfo['postcard']-1;
            $ret=true;
        }
        else if($awardtype==2 && $AwardsInfo['flowcard']>0){
            $AwardsInfo['flowcard']=$AwardsInfo['flowcard']-1;
            $ret=true;
        }
        else if($awardtype==3 && $AwardsInfo['doll']>0){
            $AwardsInfo['doll']=$AwardsInfo['doll']-1;
            $ret=true;
        }
        $flag=$SqlAwards->data($AwardsInfo)->save();
        if($ret){
            $SqlContact=D("Contact");
            $data['openid']=$openid;
            $data['awardstype']=$awardtype;
            $flag=$SqlContact->data($data)->save();
            $fail=false;
            $AwardsName=['明信片','流量卡','QQ公仔'];
            $this->data='您已成功答对'.($UserInfo['grade'])/(self::QUSTION_SCORE).'题，并恭喜您抽中了【'.$AwardsName[$awardtype-1].'】，我们将在活动结束后公布获奖名单,并将发送短信通知你领取';
        }
        if(!$flag){ 
          $this->data='系统出错,请截图后发送到公众号';
          $SqlAwards->rollback();
        }else{
          $SqlAwards->commit();
        }
      }
      if($fail){
        $this->data='您已成功答对'.($UserInfo['grade'])/(self::QUSTION_SCORE)."题，但是很遗憾，您没有抽中任何奖品，感谢您的参与";
      }
      $this->display();
      exit;
    }
  }
  protected function _checkTimeOut($SqlUser,$UserInfo,$openid,$msg){
    $user['openid']=$openid;
    $user['status']='End';
    if(strtotime("now")-strtotime($UserInfo['starttime'])>self::TIME_OUT){
      $user['firstend']=date('Y-m-d H:i:s');
      $this->data='时间到！！！已超出答题时间，本次活动已经结束，谢谢参与！';
      $SqlUser->data($user)->save();
      $this->display();
      exit;
    }
  }
  protected function _getRiddle($openid){
    $SqlAnswer=D("Answer");
    $SqlRiddle=D("Riddle");
    $SqlUser=D("User");
    $AnswerInfo=$SqlAnswer->where('openid="%s"',$openid)->select();
    $RiddleInfo=$SqlRiddle->select();
    if(!$RiddleInfo){
      $this->data='抽出题目错误！！！系统异常，请回复“取消”，之后召唤客服';
      $this->display();
      exit;
    }
    //去除重复题目
    for($id=0;$id<sizeof($AnswerInfo);$id++){
      $aid=$AnswerInfo[$id]['riddleid'];
      unset($RiddleInfo[$aid]);
    }
    unset($id);unset($aid);
    if(sizeof($RiddleInfo)===0){
    	$user['openid']=$openid;
        $user['status']='End';
        $SqlUser->data($user)->save();

        $this->data='很遗憾，您没有答对'.(self::END_QUSTION_NUM).'题，小喵的题库空啦，谢谢参与本次活动！！';
        $this->display();
        exit();
    }
    //打乱数组
    $keys = array_keys($RiddleInfo);   
    shuffle($keys);   
    $random = array();   
    foreach ($keys as $key){  
      $random[$key] = $RiddleInfo[$key];
    } 
    //数组重新索引
    $RiddleInfo=array_merge($random);
    //按难度排序
    usort($RiddleInfo, function($a, $b) {
        $al = $a['difficult'];
        $bl = $b['difficult'];
        if ($al == $bl)
            return 0;
        return ($al < $bl) ? -1 : 1;
    });
    //抽出谜题
    $this->data='问题：'.$RiddleInfo[0]['question'];
    $this->display();
    $data['openid']=$openid;
    $data['finalquestion']=$RiddleInfo[0]['id'];
    $SqlUser->data($data)->save();
    //记录已答题目
    unset($data);
    $data['openid']=$openid;
    $data['riddleid']=$RiddleInfo[0]['id'];
    $SqlAnswer->data($data)->add();
  }
  protected function _judge_Answer($openid,$msg){
    $SqlRiddle=D("Riddle");
    $SqlUser=D("User");
    //刷题封禁
    $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
    if(strtotime("now")-strtotime($UserInfo['finalanswer'])<1){
      $this->data='刷题太快啦，请休息下吧，注意哦！刷题太快将会被小喵禁止参加本次活动！';
      $data['openid']=$openid;
      $data['foultime']=$UserInfo['foultime']+1;
      $SqlUser->data($data)->save();
      unset($data);
      $this->display();
      exit;
    }
    $FinalRiddle=$UserInfo['finalquestion'];
    if($FinalRiddle==NULL){
      return;
    }
    $RiddleInfo=$SqlRiddle->select();
    if(!$RiddleInfo){
        $this->data='获取答案失败，请重试';
        $this->display();
        exit();
    }
    $SqlAnswer=D("Answer");
    $AnswerData['openid']=$openid;
    $AnswerData['riddleid']=$FinalRiddle;
    unset($data);
    $data['openid']=$openid;
    $data['finalanswer']=date('Y-m-d H:i:s');
    if(strstr($msg,$RiddleInfo[$FinalRiddle]['answer']) OR strstr($msg,$RiddleInfo[$FinalRiddle]['answer2'])){
      $data['grade']=$UserInfo['grade']+self::QUSTION_SCORE;
      $AnswerData['YesOrNot']=1;
      $this->data2='恭喜您，回答正确，已正确回答了'.$data['grade']/(self::QUSTION_SCORE).'题\n\n';
    }
    else{
      $AnswerData['YesOrNot']=0;
      $this->data2='很遗憾，回答错误\n已正确回答了：'.$UserInfo['grade']/(self::QUSTION_SCORE).'题\n\n';
    }
    $SqlUser->data($data)->save();
    $AnswerData['AnswerTime']=date('Y-m-d H:i:s');
    $SqlAnswer->data($AnswerData)->save();
  }
  public function rank(){
    $mydata=false;
    $function=false;
    if(IS_GET){
      $openid=I('get.openid',"");
      if($openid!=""){
        $mydata=true;
      }
    }
    $this->mydata=$mydata;
    $this->function=$function;

    $SqlUser=D("User");
    $SqlUser->join('Contact ON Contact.openid = User.openid');
    $UserInfo=$SqlUser->order('finalanswer ASC')->field('User.openid,nickname,grade,awardstype')->select();
    for($myrank=0;$myrank<sizeof($UserInfo);$myrank++){
      if($UserInfo[$myrank]['openid']===$openid){
        $UserMy=$UserInfo[$myrank];
        break;
      }
    }
    if(sizeof($UserMy)===0){
      $this->mygrade='暂无喵币';
      $this->myrank='暂无排名';
      $this->mysend='暂无';
      $this->myreceive='暂无';
    }
    else{
      $this->nickname=$UserMy['nickname'];
      $this->mygrade=$UserMy['grade'];
      $this->myrank=$myrank+1;
    }
    $AwardsName=['无','明信片','流量卡','QQ公仔'];
    for($id=sizeof($UserInfo);sizeof($UserInfo)-$id<10;$id--){
      $toprank[$id]['id']=$id;
      $toprank[$id]['nickname']=$UserInfo[$id-1]['nickname'];
      $toprank[$id]['awardstype']=$AwardsName[$UserInfo[$id-1]['awardstype']];
    }
    $this->toprank=$toprank;
    $this->display();
  }
  public function riddlebegin(){
    $this->_checkReceive($openid,$nickname,$msg);
    $SqlUser=D("User");
    $SqlContact=D("Contact");
    $result=$SqlContact->where('openid="%s"',$openid)->count();
    if($result==0){
      $this->data='欢迎参加小喵抽奖活动\n\n每个人将有'.(self::TIME_OUT/60).'分钟时间答题，且答题期间无法暂停\n活动结束时间：\n9月15日晚上8点\n\n注：\n您所输入的手机号仅作为领取活动奖品凭据\n\n回复“取消”退出抽奖活动\n\n请输入您的手机';
      $this->display();
      exit;
    }
    $result=$SqlUser->where('openid="%s"',$openid)->count();
    if($result==0){
      //对于新参加用户，插入数据
      $user['openid']=$openid;
      $user['status']='willstart';
      $SqlUser->data($user)->add();
      $this->data='欢迎参加小喵抽奖活动\n\n每个人将有'.(self::TIME_OUT/60).'分钟时间答题，且答题期间无法暂停\n活动结束时间：\n9月15日晚上8点\n\n注：\n您所输入的手机号仅作为领取活动奖品凭据\n\n回复“取消”退出抽奖活动\n\n回复“开始”开始计时';
      $this->display();
      exit;
    }
    else{
      $UserInfo=$SqlUser->where('openid="%s"',$openid)->find();
      switch ($UserInfo['status']) {
        case 'willstart':
          $this->data='欢迎参加抽奖活动\n\n每个人将有'.(self::TIME_OUT/60).'分钟时间答题，且答题期间无法暂停\n活动结束时间：\n9月15日晚上8点\n\n注：\n您所输入的手机号仅作为领取活动奖品凭据\n\n回复“取消”退出抽奖活动\n回复“开始”开始计时';
          break;
        case 'End':
          if($UserInfo['grade'] >= self::END_QUSTION_NUM){
            $SqlContact=D("Contact");
            $ContactInfo=$SqlContact->where('openid="%s"',$openid)->find();
            $AwardsName=['明信片','流量卡','QQ公仔'];
            $awardstype=$ContactInfo['awardstype'];
            if($awardstype>0 && $awardstype<=3){
              $this->data='本活动只能参与一次，恭喜您抽中了【'.$AwardsName[$awardstype-1].'】，我们将在活动结束后公布获奖名单,并将发送短信通知您领奖\n\n回复“取消”回到正常模式';
            }
            else{
              $this->data='感谢您参与本次活动，很遗憾你没有抽到任何奖品\n\n回复“取消”回到正常模式';
            }
          }
     	    else{
             $this->data='本活动只能参与一次，很遗憾您没有答对'.(self::END_QUSTION_NUM).'题，无法获得抽奖机会,感谢您的参与\n\n回复“取消”回到正常模式';
          }
          break;
        case 'Starting':
          $this->_endActivity($SqlUser,$UserInfo,$openid,$msg);
          $this->_checkTimeOut($SqlUser,$UserInfo,$openid,$msg);
          $this->data='喵，赶紧答题，还有时间，快！快！快！\n\n回复“取消”回到正常模式';
          break;
        case 'Baned':
          $this->order='noenter';
          $this->data='喵！坏人，尝试违规刷题，您已被小喵封印，小喵不准您参加本次活动啦！';
          break;
        default:
          $this->data='喵，系统错误，请回复“取消”，之后召唤客服';
          break;
      }
      $this->display();
      exit;
    }
  }
}