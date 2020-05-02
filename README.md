PHP Async Clients benchmark (Amphp, ReactPHP, Guzzle)
============================

### Http request with saving data to file

Docker:
Concurrency = 25, iterations=10

|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           |guzzle(multicurl)|1.7345  |  2.3703  |1.8946 |
|           | amphp   | 1.4945    | 2.2807   | 1.7511 |
|           | reactphp|  3.7657   | 4.1934   | 3.9941   |
| 100 queries|               |             |           |           |
|           |guzzle(multicurl)| 5.6384  |  7.5598  | 6.0287  |
|           | amphp   | 10.8207    | 12.8282   |  11.4251  |
|           | amphp (-c100)  | 4.4791   | 24.4778   | 8.3344  |
|           | reactphp| 9.3227  | 9.7782 | 9.4414  |
| 500 queries|               |             |           |           |
|           |guzzle(multicurl)|21.9477 |36.5856|28.8197|
|           | amphp   | 101.1285 | 137.5680 |117.6780|
|           | reactphp| 21.2281| 31.7487|26.1375|
| 2000 queries|               |             |           |           |
|           |guzzle(multicurl)|86.3061|139.7627 |115.9115|
|           | reactphp|86.6550 | 115.3414|104.7146|
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)|248.9 | 260.0907 | 255.7737|
|           | reactphp| 208.5986 | 228.2414 | 217.3939 |


Production server

|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           |guzzle(multicurl)|1.5025  |  1.9986  |1.7009 |
|           | amphp   | 1.4691    | 2.4819   | 1.7389 |
|           | reactphp|  7.0732   | 7.6412   | 7.1580   |
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)| 135.9078  |  143.1398  | 139.3505  |
|           | reactphp| 95.6756  | 111.0209 | 98.6829  |
| 6000 queries|               |             |           |           |
|           |guzzle(multicurl)| 177.1444  |  172.1199  | 174.07613  |
|           | reactphp| 139.5903  |  159.0747 | 146.9556  |