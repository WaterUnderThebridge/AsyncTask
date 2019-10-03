#/bin/bash
docker rm -f swoole_task
docker run --name=swoole_task  -itd -p 9501:9501 -v `pwd`:/root/    e0391b5077a7 start.sh
