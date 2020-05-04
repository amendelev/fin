<?php

namespace App\Carreta;

use Medoo\Medoo;
use UnexpectedValueException;
use DateTime;
use DateTimeZone;
use DateInterval;
use DatePeriod;

class Carreta
{
    
    var $_db;
    var $_files;
    function set_db($path) {
        $this->_db=new Medoo([
            'database_type' => 'sqlite',
            'database_file' => $path,
        ]);
        $this->_db->query('PRAGMA synchronous=NORMAL');
//        $this->_db->query('PRAGMA journal_mode=WAL');

        $this->_files=dirname($path).'/files';
    }
    function files() {
        return $this->_files;
    }

    function db() {
        $db=$this->_db;
        if (!$db) {
            throw new UnexpectedValueException('выполните $car->set_db');
        };
        return $db;
    }
    function q($x) {
        return $this->db()->quote($x);
    }
    function passo_run() {
// считаем что задание уже есть
        $tarefa=$this->tarefa();
        $this->tarefa_com();
        $this->set_tarefa($tarefa->sid);

        set_time_limit(0);
        $runid=uniqid();
        $need_next=true; $err_count=0;

        $this->log("$runid comecar {$tarefa->sid}");
        do {
// начали задание   
            $exec=$this->executor('next');
            if ($exec->empty()) {// не нашлось заданий, для выполнения
                $this->log("$runid empty");
                $need_next=false;
            }else{
                $passo=$exec->passo();
                $this->log("$runid passo com {$tarefa->sid} {$passo->dt} {$passo->id}");
                $res_is=null;
                try {
                    $exec->receber_e_conservar();
                    $res_is=true;
                }catch(Throwable $ex) {
                    $res_is=false;
                    $err_count++;
                    $this->log($ex, "$runid passo exception {$tarefa->sid} {$passo->dt} {$passo->id}");
                };
                $exec->passo_term($res_is);
                $suc=$res_is ? 'SUCESSO' : 'MALOGRO';
                $this->log("$runid $suc passo term {$tarefa->sid} {$passo->dt} {$passo->id}");
            };
            $exec=null;
        } while ($need_next && $err_count<10);     
        $is_ok=$this->tarefa_term_tentar();
        $suc=$is_ok ? 'SUCESSO' : 'MALOGRO';
        $this->log("$runid $suc terminar {$tarefa->sid} err_count=$err_count");
        return true;
    }
    function tarefa_com() {
        $tarefa=$this->tarefa();
        $where=array(
            'sid'=>$tarefa->sid, 
            'exec_com_ts'=>null);
        $change=array('exec_com_ts'=> $this->now());
        $this->db()->update('tarefa', $change, $where);
    }
    function tarefa_term_tentar() {
        $tarefa=$this->tarefa();
        $has_not_ready=$this->db()->has('passo', array(
            'sid'=>$tarefa->sid,
            'res_is'=>0,
        ));
        if ($has_not_ready) {
        }else{
            $where=array('sid'=>$tarefa->sid);

            $change=array('exec_term_ts' => $this->now());
            $this->db()->update('tarefa', $change, $where);
        };

        return !$has_not_ready;
    }


    function executor($mode, $xid=null) {
        if ('id'==$mode) {
            $sid=$this->tarefa()->sid;
            $passo=$this->passo_get(array('id'=>$xid, 'tarefa'=>$sid));
            if (empty($passo)) {
                throw new UnexpectedValueException('passo не найдено');
            };
        }elseif ('next'==$mode) {
            $sid=$this->tarefa()->sid;
            $uniqid=uniqid();
            $now=$this->now();
            $min5ate=$this->now('-5 min');
            $change=array(
                'uniqid'=>$uniqid,
                'exec_pid'=>getmypid(),
                'com_ts'=>$now,
                "conta[+]" => 1,
            );
            $where_sql=<<<EEFEF
WHERE
    tarefa={$this->q($sid)}
    AND res_is=0
    AND
    ( 
        com_ts IS null
        OR ( term_ts is not null AND term_ts>=com_ts AND term_ts < {$this->q($now)} ) 
        OR ( com_ts <= {$this->q($min5ate)} ) -- процесс убился по фатальной ошибке
    )
ORDER BY conta,dt desc
LIMIT 1
EEFEF;
            $where=Medoo::raw($where_sql);
            $this->db()->update('passo', $change, $where);
            if ($this->db_err()) {
                throw new UnexpectedValueException('update failed');
//echo "sql={$this->db()->last()}\n";
//echo "err={$this->db()->error()[2]}";
            };
            $passo=$this->passo_get(array('uniqid'=>$uniqid));
        }else{
            throw new UnexpectedValueException('неизвестный режим');  
        };
        $exec=new Executor;
        $exec->set_carreta($this);
        $exec->set_passo($passo);// может быть пустым(!)
        return $exec;
    }
    function db_err() {
        $err=$this->db()->error();
        $is=$err[0]!='0000';
        return $is;
    }
    function now($modi=null) {
        $date_utc = new DateTime("now", new DateTimeZone("UTC"));
        if ($modi) {
            $date_utc->modify($modi);
        };
        $ret=$date_utc->format("Y-m-d H:i:s");
        return $ret;
    }
    function passo_get($arr) {
        $ret=$this->db()->get('passo', '*',$arr);
        return empty($ret) ? $ret : (object) $ret;
    }

    var $_logger;
    function set_logger($logger) {
        $this->_logger=$logger;
    }
    function logger() {
        return $this->_logger;
    }
    function log($x, $par=null) {
        if ($this->logger()) {
            if (!$par) {
                $par=array();
            }elseif (is_scalar($par)) {
                $par=array($par);
            };
            if ($x instanceof Throwable) {
                $this->logger()->error($x, $par);
            }else{
                $this->logger()->info($x, $par);
            };
        }
    }

    var $_tarefa;
    function set_tarefa($sid) {
        $db=$this->db();
        $all=$db->get('tarefa', '*', array('sid'=>$sid));
        if (empty($all)) {
            throw new UnexpectedValueException('tarefa not found');
        };
        $this->_tarefa=(object) $all;
    }
    function tarefa() {
        $all=$this->_tarefa;
        if (empty($all)) {
            throw new UnexpectedValueException('no tarefa');
        };
        return $this->_tarefa;
    }
    function tarefa_has($sid) {
        $db=$this->db();
        return $db->has('tarefa', array('sid'=>$sid));
    }
    function passo_apartar() {
        $db=$this->db();
        $wh=array();
        $wh['tarefa']=$this->tarefa()->sid;
        $pstmt=$db->delete('passo', $wh);
        return $pstmt->rowCount();
    }
    function passo_encher() {
        $tarefa=$this->tarefa();
        $de=$this->dt($tarefa->de_dt);
        $ate=$this->dt($tarefa->ate_dt);
        if ($de && $ate && $de<=$ate) {
        } else {
            throw new UnexpectedValueException('wrong de_dt ate_dt');
        };
        $uma = new DateInterval('P1D');
        $ate->modify( '+1 day' );
        $period=new DatePeriod($de, $uma, $ate);
        $db=$this->db();
        $encher=0;
        $sid=$this->tarefa()->sid;
        foreach ($period as $dt) {
            $dts=$dt->format('Y-m-d');
            $all=array('tarefa'=>$sid, 'dt'=> $dts);
            if (!$db->has('passo', $all)) {
                $db->insert('passo', $all);
            };
            $encher=$encher+1;
        };
        return $encher;
    }
    function dt($ymd) {
        return DateTime::createFromFormat('!Y-m-d', $ymd);
    }

}
