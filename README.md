PHP Async Clients benchmark (Amphp, ReactPHP, Guzzle)
============================

Articles with explanation  [[ENG]](https://dev.to/insolita/which-http-client-is-faster-for-web-scraping-c95)  [[RUS]](https://medium.com/@DonnaInsolita/%D0%B2%D0%B4%D0%BE%D1%85%D0%BD%D0%BE%D0%B2%D0%B8%D0%B2%D1%88%D0%B8%D1%81%D1%8C-%D0%B7%D0%B0%D0%BD%D0%B8%D0%BC%D0%B0%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D1%8B%D0%BC-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D0%B2%D1%8C%D1%8E-%D0%BD%D0%B0-%D0%BA%D0%B0%D0%BD%D0%B0%D0%BB%D0%B5-moreview-c-%D1%81%D0%B5%D1%80%D0%B3%D0%B5%D0%B5%D0%BC-%D0%B6%D1%83%D0%BA%D0%BE%D0%BC-%D0%B8-%D1%86%D0%B8%D0%BA%D0%BB%D0%BE%D0%BC-%D1%81%D1%82%D0%B0%D1%82%D0%B5%D0%B9-fast-web-f9715b21517f)

### Http request with saving data to file

Docker:
Concurrency = 25, iterations=10

|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           | guzzle(multicurl)| 1.938 | 2.1224 | 2.0283 |
|           | amphp| 1.8409 | 2.6186 | 2.1615 |
|           | reactphp|  6.7534 | 6.8043 | 6.7757 |
| 100 queries|               |             |           |           |
|           |guzzle(multicurl)|  5.1422 | 6.4187 | 5.6293 |
|           | amphp   |  4.6551 | 6.8954 | 4.9794 |
|           | reactphp|   8.5821 | 9.354 | 8.8906 |
| 500 queries|               |             |           |           |
|           |guzzle(multicurl)|21.6166 |37.4057|29.6603|
|           | amphp   | 19.1308 | 27.2957 |23.5737|
|           | reactphp| 20.1959 | 30.6965 | 25.1002 |
| 2000 queries|               |             |           |           |
|           |guzzle(multicurl)| 93.5699 | 121.8421 | 111.7504|
|           | amphp| 65.8536 | 94.9016 | 84.1415 |
|           | reactphp|73.346 | 113.252 | 96.7179|
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)| 218.3731 | 236.0503 | 228.1774|
|           | amphp| 164.6433 | 183.1089 | 172.692 |
|           | reactphp| 199.0026 | 206.5144 | 203.9312 |


Production server

|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           |guzzle(multicurl)|  1.4136 | 1.5777 | 1.5056 |
|           | amphp   |  1.6166 | 2.2392 | 1.8866 |
|           | reactphp|  6.9168 | 7.0695 | 7.0179 |
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)|    |     |    |
|           | amphp|  65.9951 | 75.299 | 70.2124 |
|           | reactphp| 81.8953 | 87.0721 | 83.5715 |
| 6000 queries|               |             |           |           |
|           |guzzle(multicurl)|    |     |    |
|           | amphp|  93.9942 | 108.4978 | 100.1591 |
|           | reactphp| 141.9791 | 150.1749 | 144.8111 |