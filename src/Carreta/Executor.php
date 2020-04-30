<?php
namespace App\Carreta;

use GuzzleHttp\Client;
//use GuzzleHttp\Psr7\StreamWrapper;
use UnexpectedValueException;

class Executor
{
    var $_car;
    function set_carreta($car) {
        $this->_car=$car;
    }
    var $_passo;
    function set_passo($passo) {
        $this->_passo=$passo;
    }
    function empty() {
        return empty($this->_passo);
    }
    function passo() {
        if (empty($this->_passo)) {
            throw new UnexpectedValueException('no passo');
        };
        return $this->_passo;
    }
    function db() {
        return $this->_car->db();
    }
    function files() {
        return $this->_car->files();
    }
    var $_files_out;
    function files_out() {
        $res=$this->_files_out;
        if (!$res) {
            $dir=dirname($this->files());
            $res="{$dir}/byday";
            $this->_files_out=$res;
        };
        return $res;
    }
    function tarefa() {
        return $this->_car->tarefa();
    }

    function receber_e_conservar() {
// сохранить файл, вернуть путь до файла        
        $path=$this->receber();
// path null - значит 
// в этот день не было торгов
        if ($path) {
            $line_all=$this->process($path);
            $agg_row=$this->agg_minuta($line_all);
            $this->conservar($agg_row);
        };
        return true;
    }

// получить файлик и сохранить локально
    function receber() {
        $tarefa=$this->tarefa();
        $passo=$this->passo();
//print_r($passo);        
        $files=$this->files();
        $path="{$files}/{$tarefa->sid}/{$passo->dt}.file";
        if (!is_file($path)) {
            $responce=$this->guzzle_request($tarefa, $passo->dt);
            $status=$responce->getStatusCode();
            if (200==$status) {
                $tmp = tempnam("/tmp", "rece-");
                $ha=fopen($tmp, 'w');
                $body=$responce->getBody();
                while (!$body->eof()) {
                    $str=$body->read(64*1024);
                    fwrite($ha, $str);
                };
                @fclose($ha);
                $this->deslocar($tmp, $path);
            }elseif (404==$status) {
                $path=null;
            }else{
                throw new UnexpectedValueException("passo {$passo->id}: вернулся $status"); 
            };
        };
        return $path;
    }
    function parse_line($str) {
        $str=rtrim($str,"\n");
        $all=explode("\t", $str);
#TIMESTAMP_MSK____ ________id      1KUPI_2PRODAY
#price   volume  oi      order_id
        $keys=array('ts_msk','kod','kupro','tsena','vol','oi','order_id');
        $ret=array_combine($keys, $all);
        return $ret;
    }
    function row_csv_vals($row) {
        list($date,$time)=explode(' ', $row['ts_msk']);
        $vals=array(
            $date,
            $time,
            $row['kupro'],
            $row['tsena'],
            $row['vol'],
            $row['oi'],
            $row['shag'],
            $row['lot'],
            $row['znak'],
            $row['vol_lot'],
            $row['tsena_rub'],
            $row['fr'],
            $row['sum_kup'],
            $row['sum_prod'],
            $row['tsena_sr'],
        );

        return $vals;
    }
    function conservar($agg_row) {
// сохраняем в базе
// на время отладки сохраняем в файлах. Позже уберем
        $passo=$this->passo();
        $tarefa=$this->tarefa();
        $where=array(
            'tarefa'=>$passo->tarefa,
            'passo'=>$passo->id,
        );
        $this->db()->delete('instrument',$where);
        $fin=array('finid' => $tarefa->finid );
        $all=array();
        foreach ($agg_row as $row) {
            $insert=array_merge($row, $where, $fin );
            $this->db()->insert('instrument', $insert);
            $all[]=$insert;
        };
$this->conservar_csv($all);// отладка(!)
        return true;
    }
    function conservar_csv($all) {// отладка(!)
        $passo=$this->passo();
        $tarefa=$this->tarefa();
        $files=$this->files_out();
        $dir="{$files}/{$tarefa->sid}";
        @mkdir($dir, 0775);
        $path="{$dir}/{$passo->dt}.csv";
        $ha=fopen($path, 'w');
        ftruncate($ha, 0);
        foreach ($all as $row) {
            $vals=$this->row_csv_vals($row);
            fputcsv($ha, $vals, ';');
        };
        @fclose($path);
        @chmod($path,0664);
    }


    function agg_minuta($process) {
        $prev=array(
            'vol_lot'=>0,
            'fr'=>0,
            'sum_kup'=>0,
            'sum_prod'=>0,
            'ts_msk'=>null,
        );
        $row=array(
            'shag'=>0.01,
            'lot'=>10,
            'znak'=>null,
            'vol_lot'=>null,
            'tsena_rub'=>null,
            'fr'=>null,
            'sum_kup'=>null,
            'sum_prod'=>null,
            'tsena_sr'=>null,
        );
        $count=0;
        foreach ($process as $line) {
            if ($count) {
                if ($this->minuta_proshla($line['ts_msk'], $prev['ts_msk'])) {
                    yield $this->agg_minuta_trim($prev);
                };
            };
            $row=array_merge($row, $line);
            $row['znak']= 1==$line['kupro'] ? 1 : -1;
            $row['vol_lot']= $row['vol']*$row['znak']+ $prev['vol_lot'];
            $row['tsena_rub']= $row['tsena']*$row['shag'];
            $row['fr']=$row['vol']*$row['lot']*$row['znak']*$row['tsena_rub']/1000.0+$prev['fr'];
            $row['sum_kup']= $row['znak']>0 
                ? $row['vol']*$row['lot']*$row['tsena_rub']/1000.0 + $prev['sum_kup']
                : $prev['sum_kup'];
            $row['sum_prod']= $row['znak']<0 
                ? $row['vol']*$row['lot']*$row['tsena_rub']/1000.0 + $prev['sum_prod']
                : $prev['sum_prod'];
            $row['tsena_sr']=$row['vol_lot']
                ? 1000.0*$row['fr']/$row['vol_lot']/$row['lot']
                : '-';
            $prev=$row;
            $count++;
        };
        if ($count) {
            yield $this->agg_minuta_trim($row);
        };
    }
    function minuta_proshla($d1, $d2) {
// если началась новая минута = true
        $d1x=$this->minuta_trim($d1);
        $d2x=$this->minuta_trim($d2);
        $ret=$d2x != $d1x;
//$ret1= $ret ? '-DA-' : 'net';
//echo "minuta_proshla $ret1 $d1x $d2x\n";
        return $ret;
    }
    function minuta_trim($d) {
        return substr($d, 0, -3);// обрезаем секунды
    }
    function agg_minuta_trim($row) {
        $row['ts_msk']=$this->minuta_trim($row['ts_msk']);
        return $row;
    }
    function process($file) {
        try {
            $base=dirname(dirname($this->files()));
            $bin="{$base}/bin";
//            $log="{$base}/var/log/process.txt";
            $cmd="zcat {$file} | python {$bin}/tofile.py";
            if ('.txt'==substr($file,-4)) {
                $cmd="cat {$file}";
            };
            $handle=popen($cmd, "r");
            while (($buffer = fgets($handle, 4096)) !== false) {
                if ('#'==substr($buffer,0,1)) {
                    continue;
                };
                $all=$this->parse_line($buffer);
                yield $all;
            };
        } finally {
            if ($handle) {
                @pclose($handle);
            };
        }
    }
    function guzzle_request($tarefa,$dt) {// можно тестировать!
// http://erinrv.qscalp.ru/2020-04-03/GAZP.2020-04-03.Deals.qsh"        
        $ret=null;
        $tip=$tarefa->tip;
        if ($tip=='qscalp') {
            $client = new Client([
                'base_uri' => 'http://erinrv.qscalp.ru',
                'connect_timeout' => 10,
                'timeout'  => 30,
                'http_errors'=>false,
            ]);
            $path="/{$dt}/{$tarefa->finid}.{$dt}.Deals.qsh";
            $ret=$client->request('GET', $path, array('stream'=>true));
        }else{
            throw new UnexpectedValueException("не реализовано"); 
        };
        return $ret;
    }
    function deslocar($tmp, $nov) {
        $dir=dirname($nov);
        @mkdir($dir, 0775);
        $ok=copy($tmp, $nov);
        @unlink($tmp);
        @chmod($nov, 0664);
        return $ok;
    }

}
