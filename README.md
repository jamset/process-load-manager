# Process & Load Manager (Pm/Lm)
ReactPHP based process manager, allowing to control loading and automatically stop or pause processes if they exceed 
set CPU usage or MemFree limit. 

Note: currently functioning only on Ubuntu/Debian OS (because of 
[CommandsExecutor](https://github.com/jamset/commands-executor) module limitations)

##Install

`composer require jamset/process-load-manager`

##Description

This module aim is to protect a node against overloading by controlling load size of every process
that launched by Pm/Lm through any queue system (such as RabbitMQ, Gearman or other). 

Where process is worker that do any useful work. 

###Features

- Can pause/continue processes and so important data will not be loosed by termination

- Can terminate processes by SIGTERM or by sending command in process to throw an Exception that will interrupt execution

I.e. Gearman (http://gearman.org/) worker (queue handler on the schema) block any signal besides SIGKILL, so become impossible to correctly 
send SIGTERM (and so handle termination, do something during it) from Pm and correct termination become possible only 
from inside a process that have to be terminated. And Pm send to such process JSON with PIDs to terminate through ZMQ PUB 
socket. Where all connected processes are subscribers and they search their PID in such JSON and if found - throw 
relevant exception, that have to be handled in process and lead to the conclusion of a script, allowing to send script's 
results somewhere if needed or just to make immediate termination. In default mode to make termination Pm just sends SIGTERM

- You can choose size of MemFree and CPU usage limit, and it will balance processes numbers in this limits. 

Note: For correct tasks execution is needed module that will handle task, that wasn't complete correctly because of force termination.

##Schema

![Process & Load manager schema](https://github.com/jamset/process-load-manager/raw/master/images/pm-lm-schema.jpg)

##Examples

```php

    $this->manager = new React\ProcessManager\Pm();
    
    
    $this->manager->setProcessManagerDto($this->gearmanDto->getProcessManagerDto());
    
        
        
        
        
    //case described in Features    
    $this->manager->setSigTermBlockingAgent(true); 
    
    //main work
    $this->manager->manage();
    
    //if PM loading as task of high level, i.e. in Gearman
    $this->manager->getExecutionDto()->setExecutionMessage("PM with id " . $taskId . " going to finish.");
    $this->manager->getExecutionDto()->setTaskId($taskId);
    
    //send result into queue to handle it with TasksInspector: report a problem or repeat task's execution if error exists 
    $this->job->sendComplete(serialize($this->manager->getExecutionDto()));
```


$processManagerDto = new ProcessManagerDto();

//Number of tasks, that have to be handled by Pm&Lm
$processManagerDto->setTasksNumber($tasksNumber);

//its code showed lower, implementation for Laravel console command, but it could any command, launched by shell 
//(by [proc_open](http://php.net/manual/ru/function.proc-open.php) function)
$processManagerDto->setLoadManagerProcessCommand("php artisan react:load-manager");

//the same. Any command, by which launched process, that handles tasks from queue and execute specific work
$processManagerDto->setWorkerProcessCommand("php artisan gearman:fetch:stat:worker);

$processManagerDto->setModuleName("ProcessManager " . $moduleName);

$pmLmSocketsParams = new PmLmSocketsParamsDto();

$pmLmSocketsParams->setPmLmRequestAddress("tcp://127.0.0.1:6267");
$processManagerDto->setPmLmSocketsParams($pmLmSocketsParams);

$loadManagerDto = new LoadManagerDto();

//in percentage
$loadManagerDto->setMemFreeUsagePercentageLimit(20);
$loadManagerDto->setCpuUsagePercentageLimit(90);
$loadManagerDto->setModuleName("LoadManager " . $moduleName);
$loadManagerDto->setPmLmSocketsParams($pmLmSocketsParams);
$loadManagerDto->setStandardMemoryGap(200000); //Kb

$processManagerDto->setLoadManagerDto($loadManagerDto);

$performerSocketsParams = new PerformerSocketsParamsDto();
$performerSocketsParams->setPublisherPmSocketAddress("tcp://127.0.0.1:6268");
$processManagerDto->setPerformerSocketsParams($performerSocketsParams);

###Example of connecting script (Service) to the module:

Init TerminatorPauseStander

```php
$this->terminatorPauseStander = new React\ProcessManager\TerminatorPauseStander();
```

and then put its method that handle termination and standing on pause/continue on many places in your code.
(the more often it will be called the faster will be the reaction to the Pm commands)

```php

$this->terminatorPauseStander->checkPmCommand();

```
