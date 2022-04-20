SwooleFramework的压力测试
----
本测试是使用swoole扩展作为底层Server框架的,其他驱动暂未测试.
建议使用swoole扩展，性能最佳。
```shell
ab -c 100 -n 100000 http://127.0.0.1:8888/hello/index/
This is ApacheBench, Version 2.3 <$Revision: 655654 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking 127.0.0.1 (be patient)
Completed 10000 requests
Completed 20000 requests
Completed 30000 requests
Completed 40000 requests
Completed 50000 requests
Completed 60000 requests
Completed 70000 requests
Completed 80000 requests
Completed 90000 requests
Completed 100000 requests
Finished 100000 requests


Server Software:        Swoole
Server Hostname:        127.0.0.1
Server Port:            8888

Document Path:          /hello/index/
Document Length:        11 bytes

Concurrency Level:      100
Time taken for tests:   10.717 seconds
Complete requests:      100000
Failed requests:        0
Write errors:           0
Total transferred:      27500000 bytes
HTML transferred:       1100000 bytes
Requests per second:    9330.83 [#/sec] (mean)
Time per request:       10.717 [ms] (mean)
Time per request:       0.107 [ms] (mean, across all concurrent requests)
Transfer rate:          2505.84 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    1   1.0      1       9
Processing:     1   10   5.6      8      63
Waiting:        0    7   5.4      6      62
Total:          1   11   5.5      9      63

Percentage of the requests served within a certain time (ms)
  50%      9
  66%     11
  75%     12
  80%     13
  90%     17
  95%     22
  98%     28
  99%     32
 100%     63 (longest request)
```
