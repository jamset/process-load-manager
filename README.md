# Process & Load Manager (Pm/Lm)
ReactPHP based process manager, allowing to control loading and automatically stop or pause processes if they exceed 
set CPU usage or MemFree limit. 

##Install

`composer require jamset/process-load-manager`

##Description

This module aim is to protect a node against overloading by controlling load size of every process
that launched by Pm/Lm through any queue system (such as RabbitMQ, Gearman or other). 

Where process is worker that do any useful work. 

Note: currently functioning only on Ubuntu/Debian OS (because of CommandsExecutor module limitations)

##Schema

![Process & Load manager schema](https://github.com/jamset/process-load-manager/raw/master/images/pm-lm-schema.jpg)

##Examples

###Example of Gearman queue implementation. Start Pm/Lm management:

```php
    $this->manager = new React\ProcessManager\Pm();
    $this->manager->setProcessManagerDto($this->gearmanDto->getProcessManagerDto());
    
    //Gearman worker (queue handler) block any signal besides SIGKILL, so become impossible to correctly send SIGTERM 
    //(and so handle termination, i.e. send report to TaskInspector) from Pm 
    //and correct termination become possible only from inside a process that have to be terminated. 
    //And Pm send to such process JSON with PIDs to terminate through ZMQ PUB socket.
    //Where all connected processes are subscribers and they search their PID in such JSON and if found - throw relevant exception,
    //that have to be handled in process and lead to the conclusion of a script, allowing to send script's results somewhere if needed
    //or just to make immediate termination. 
    //In other mode to make termination Pm just send SIGTERM
    $this->manager->setSigTermBlockingAgent(true); 
    
    $this->manager->manage();
    
    $this->manager->getExecutionDto()->setExecutionMessage("PM with id " . $this->gearmanDto->getTaskId() . " going to finish.");
    $this->manager->getExecutionDto()->setTaskId($this->gearmanDto->getTaskId());
    
    //send result into queue to handle it with TasksInspector: report a problem or repeat task's execution if error exist 
    $this->job->sendComplete(serialize($this->manager->getExecutionDto()));
```

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
