# Process & Load Manager (Pm/Lm)
ReactPHP based process manager, allowing to control loading and automatically stop or pause processes if they exceed 
set CPU usage or MemFree limit. 

In other words this module aim to protect a node against overloading by controlling load size of every process
that launched by Pm/Lm through any queue system (such as RabbitMQ, Gearman or other). 

Where process is worker that do any useful work. 

Note: currently functioning only on Ubuntu/Debian OS (because of CommandsExecutor module limitations)