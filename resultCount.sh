#/bin/sh
date=$1
sed -n  '/xh/p' /bak/log/swoole_task/task_${date}.log|sed "s#[',xh =>]##g"|sort -nr
