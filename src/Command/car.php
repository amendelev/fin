<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Carreta\Carreta;
use InvalidArgumentException;
use Throwable;
use DateInterval;
use DatePeriod;
use Psr\Log\LoggerInterface;
use PDO;

class car extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:car';

    protected function configure()
    {
        // the short description shown while running "php bin/console list"
        $this
        ->setDescription('исполняем')
        ->setHelp('do')
        ->addArgument('do', InputArgument::REQUIRED, 'метод car')
        ->addArgument('dop',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'som=vam kom=tam')
        ;
    }

    var $logger;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;
        parent::__construct();
    }

    function parse_arg(InputInterface $input) {
        $par=array();
        $dop=$input->getArgument('dop');
        $str=implode('&', $dop);
        parse_str($str, $par);
        $par['do']=$input->getArgument('do');
        return $par;
    }


    function execute(InputInterface $input, OutputInterface $output)
    {
//        error_reporting(E_ALL & ~E_NOTICE);        
        $now=new DateTime;
        $do=$input->getArgument('do');
        $pid=getmypid();
        $output->writeln("start $do pid=$pid now={$now->format('c')}");
        $exi=0;
        $par=$this->parse_arg($input);

        if (method_exists($this, $do)) {
            $this->{$do}($par, $output);
        }else{
            $output->writeln("no method test->{$do}(\$inp, \$out)");
            $exi=1;
        }

        $fin=new DateTime;
        $output->writeln("finish $do {$fin->format('c')}");

        return $exi;
    }

    function par(array $input, OutputInterface $output) {

           
        try {
            throw new InvalidArgumentException('invatest');
        } catch ( Throwable   $ex) {
            $msg=$ex->__toString();
            $all=array();
            $all['#code']=$ex->getCode();
            $all['#file']=$ex->getFile().':'.$ex->getLine();
//            $all['#trace']=$ex->getTrace();
            $this->logger->error($msg, $all);
        };
        $this->logger->info("after");

/*

if (false) {
            $msg=$ex->getMessage();
            $all=array();
            $all['#code']=$ex->getCode();
            $all['#file']=$ex->getFile().':'.$ex->getLine();
            $all['#trace']=$ex->getTraceAsString();
            $this->logger->error($msg, $all);
};
        };

*/
        $tarefa_db=$this->params->get('tarefa_db');
        $output->writeln("tarefa_db=$tarefa_db");


        $begin = new DateTime( '2012-08-01' );
        $end = new DateTime( '2012-08-10' );
        $end = $end->modify( '+1 day' );

        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval ,$end);

        foreach($daterange as $date){
            echo $date->format("Ymd") . "\n";
        }


    }

    function set_wal(array $input, OutputInterface $output) {
        $tarefa_db=$this->params->get('tarefa_db');
        $car=new Carreta;
        $car->set_logger($this->logger);
        $car->set_db($tarefa_db);
        $res=$car->db()->query("PRAGMA journal_mode=WAL");
        $this->dump('set_wal', $res);
    }

    function db(array $input, OutputInterface $output) {
        $tarefa_db=$this->params->get('tarefa_db');
        $car=new Carreta;
        $car->set_logger($this->logger);
        $car->set_db($tarefa_db);

        $pragma=array(
            'PRAGMA journal_mode',
            'PRAGMA synchronous',
        );
        foreach ($pragma as $sql) {
            $rs=$car->db()->query($sql);
            $row=$rs->fetch(PDO::FETCH_ASSOC);
            $this->dump('sql', $row);
        };
    }



    function passo(array $input, OutputInterface $output) {
        $sid=@$input['sid'];
        if (empty($sid)) {
            throw new InvalidArgumentException('empty sid');
        };
        $tarefa_db=$this->params->get('tarefa_db');

        $car=new Carreta;
        $car->set_logger($this->logger);
        $car->set_db($tarefa_db);
        if (!$car->tarefa_has($sid)) {
            throw new InvalidArgumentException('unknown sid');
        };
        $car->set_tarefa($sid);

//$t=$car->tarefa();
//$this->dump('tarefa',$t);

        $output->writeln("tarefa.sid=$sid");
        if ($passo=@$input['passo']) {
            $met="passo_{$passo}";
            if (method_exists($car, $met)) {
                $ret=$car->$met();
                $output->writeln("$met вернул $ret");
            }else{
                throw new InvalidArgumentException("no \$car->$met()");
            };
        }elseif ($emet=@$input['exec']) {
            $passo_id=@$input['passo_id'];
            if (empty($passo_id)) {
                throw new InvalidArgumentException('passo_id');
            };
            if ('next'==$passo_id) {
                $exec=$car->executor($passo_id);
            }else{
                $exec=$car->executor('id', $passo_id);
            };
            $met=$emet;
            if (method_exists($exec, $met)) {
                if ('process'==$met) {
                    $path='/home/amendelev/fin/fin/db/files/g24/2020-04-03.file';
                    $ret=$exec->process($path);
                    $cnt=0;
                    foreach ($ret as $part) {
                        $this->dump('part', $part);
                        if ($cnt++>10) {
                            break;
                        };
                    }
                }elseif ('agg_minuta'==$met) {
                    $path='/home/amendelev/fin/python/gazp.2020-04-03.head.txt';
                    $line_all=$exec->process($path);
                    $ret=$exec->agg_minuta($line_all);
                    $cnt=0;
                    foreach ($ret as $part) {
                        $this->dump('agg_minute', $part);
                        if ($cnt++>10) {
                            break;
                        };
                    }
                }elseif ('conservar'==$met) {
                    $path='/home/amendelev/fin/python/gazp.2020-04-03.head.txt';
                    $path='/home/amendelev/fin/fin/db/files/g24/2020-04-03.file';

                    $line_all=$exec->process($path);
                    $agg_row=$exec->agg_minuta($line_all);
                    $ret=$exec->conservar($agg_row);
                    $this->dump("Excecutor->$met вернул", $ret);
                }elseif ('passo_term'==$met) {
                    $this->dump('passo pered', $exec->passo());
                    $exec->set_process_stat('quan',123123);
                    $exec->set_process_stat('quan_min',123);
                    $exec->passo_term(true);
                    $passo=$car->passo_get(array('id'=>$exec->passo()->id));
                    $this->dump('after passo', $passo);
                }else{
                    $ret=$exec->$met();
                    $this->dump("Excecutor->$met вернул", $ret);
                };
            }else{
                throw new InvalidArgumentException("no \$car->$met()");
            };
        };
    }

    function dump($name, $var) {
        echo "dump($name, var): ",PHP_EOL;
        dump($var);
        echo "dump end",PHP_EOL;
    }


}
