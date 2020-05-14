# fin

Учебный проект. 
Таблицу обезличенных сделок ММВБ переводим из формата ежедневных qsh файлов в агреггированный поминутный вид в sqlite и csv файлы. 

Помимо собственно решения функциональной задачи преследует несколько целей:

- в недалеком будущем стать примером правильно оформленного кода: с тестами и др.
- поработать с symfony
- поработать с sqlite
- поработать с generator
- помочь мне в изучении португальского языка через названия методов

Не удалось найти парсер qsh файлов на php, поэтому был написан небольшой парсер на python использующий ```import qsh```



## Работа с проектом

```shell
# 1. добавить задание, выполнив запрос в базу данных
INSERT INTO tarefa 
(sid,tip,finid,de_dt,ate_dt,crea_ts) 
	VALUES
('gazp2019plus','qscalp', 'GAZP', '2019-01-01', '2020-04-30', current_timestamp);

# заполнить таблицу шагов
$ php bin/console app:car passo passo=encher sid=gazp2019plus

# запустить выполние в один поток 
$ php bin/console app:car passo passo=run sid=gazp2019plus >run5 2>&1 & 

# запустить выполнение в 2 потока
$ ./bin/run.bash 2 gazp2019plus

# в итоге заполнится таблица instrument
$ sqlite3 db/zd.db "SELECT count(*) FROM instrument WHERE tarefa='gazp2019plus'"
159742

# и появтся csv файлы в папках лет
$ ls db/byday/gazp2019plus
2019  2020
$ ls db/byday/sber/gazp2019plus/ | head -n3
2019-01-03.csv
2019-01-04.csv
2019-01-08.csv

```



## Легенда программного кода

```php
#Есть два класса
use App\Carreta\Carreta; // ответственен за tarefa - задание в целом
use App\Carreta\Executor; // ответственен за шаг выполнение - passo

$car=new Carreta;
$car->set_logger($this->logger);
$car->set_db($tarefa_db);
$car->set_tarefa($sid);

# Carreta порождает Executor по id
$exec=$car->executor('id', $passo_id);
# либо Carreta порождает Executor с целью выполнения по одному из алгоритмов
$exec=$car->executor('next');

# выполнение задания целиком реализовано в методе Carret->passo_run()
# по следующей схеме
        do {
// начали задание   
            $exec=$this->executor('next');
            if ($exec->empty()) {// не нашлось заданий, для выполнения
                $need_next=false;
            }else{
                $passo=$exec->passo();
                $res_is=null;
                try {
                    $exec->receber_e_conservar();
                    $res_is=true;
                }catch(Throwable $ex) {
                    $res_is=false;
                    $err_count++;
                };
                $exec->passo_term($res_is);
            };
            $exec=null;
        } while ($need_next && $err_count<10);     


# выполнение отдельного шага ( Executor -> receber_e_conservar() )
    function receber_e_conservar() {
// сохранить файл, вернуть путь до файла        
        $path=$this->receber();
// path null - значит 
// в этот день не было торгов
        if ($path) {
// получим generator перебирающий строки файла
            $line_all=$this->process($path);
// получим генератор, группирующий строки по минуте
            $agg_row=$this->agg_minuta($line_all);// $agg_row - генератор
// сохраним данные во все места
            $this->conservar($agg_row);
        };
        return true;
    }


```



## База данных

```sqlite
-- хранить в db/zd.db
CREATE TABLE tarefa (-- задания
	sid text NOT NULL,-- короткий идентификатор задания
	tip TEXT not null,-- qscalp 
	finid text not null,-- GAZP
	de_dt text not null,-- 2020-04-02 с какого получить 
	ate_dt text not null,-- 2020-04-04 до какого получить
	crea_ts text not null,-- создание записи 
	passo_ts text,-- создание шагов
	exec_com_ts text, -- когда выполнение началось
	exec_term_ts text, -- когда выполнение закончилось
	by_year bool default 1, -- хранить по году
	
	CONSTRAINT tarefa_sid PRIMARY KEY (sid)
); 

CREATE TABLE passo (-- шаги задания
	id INTEGER PRIMARY KEY AUTOINCREMENT,-- id
	tarefa TEXT not null,-- код задания
	dt text not null, -- день
	
	uniqid TEXT, -- в момент выполния ставим uniqid() для обращения к записи
	exec_pid INTEGER, -- pid процесса выполняющего
	com_ts text, -- выполнение начато
	conta INTEGER not null defalt 0,-- при каждом старте задания увеличиваем на единицу
	term_ts text, -- выполнение закончено
	
	res_is boolean default 0,-- 1 есть устраивающий конечный результат
	quan integer, -- количество строк в файле; 0 если это выходной
	quan_min integer,-- количество строк в файле по минутам
	
	CONSTRAINT passo_dt unique  (tarefa, dt)
);
CREATE UNIQUE INDEX passo_idx_uniqid ON passo (uniqid);


CREATE TABLE instrument (-- данные по минутами
-- задание
	tarefa TEXT not null,-- получено по задаче
	passo INTEGER not null,-- получено по шагу
	finid TEXT not null,-- код финансового инструмента дублирую
-- из файла
	ts_msk TEXT not null,-- 03.04.2020 10:02 - время по москве
	kod INTEGER not null, -- 3150572835
	kupro INTEGER, 1 - покупка 2 продажа
	order_id INTEGER, 0 для газпрома
	tsena INTEGER not null,-- 18674
	vol INTEGER not null, -- 54
	oi INTEGER not null, -- 0 для газпрома
	
-- высчитываем
	shag REAL,  -- 0.01 для газпрома
	lot INTEGER, -- 10 для газпрома
	znak INTEGER, -- 1 или -1
	vol_lot INTEGER, 
	tsena_rub REAL,
	fr REAL,
	sum_kup REAL,
	sum_prod REAL,
	tsena_sr REAL
);




```

Поколдовал с базой данных.
Выяснилось что мне подходит режим WAL 
По умолчанию включен режим `synchronous=FULL journal_mode=DELETE`
Он подходит только для shell скриптов, где разные процессы иногда меняют базу.

```sqlite
-- выполнить один раз и навсегда
PRAGMA journal_mode=WAL 
-- выполнять при каждом коннекте
PRAGMA synchronous=NORMAL
```

