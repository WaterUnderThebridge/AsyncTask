#/bin/sh

sed -n  '/xh/p' /bak/log/swoole_task/task_2019-10-03.log|sed "s#[',xh =>]##g"|sort -nr
