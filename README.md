PHP Async Clients benchmark (Amphp, ReactPHP, Guzzle)
============================

**Articles with explanation**  [[ENG]](https://dev.to/insolita/which-http-client-is-faster-for-web-scraping-c95)  [[RUS]](https://medium.com/@DonnaInsolita/%D0%B2%D0%B4%D0%BE%D1%85%D0%BD%D0%BE%D0%B2%D0%B8%D0%B2%D1%88%D0%B8%D1%81%D1%8C-%D0%B7%D0%B0%D0%BD%D0%B8%D0%BC%D0%B0%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D1%8B%D0%BC-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D0%B2%D1%8C%D1%8E-%D0%BD%D0%B0-%D0%BA%D0%B0%D0%BD%D0%B0%D0%BB%D0%B5-moreview-c-%D1%81%D0%B5%D1%80%D0%B3%D0%B5%D0%B5%D0%BC-%D0%B6%D1%83%D0%BA%D0%BE%D0%BC-%D0%B8-%D1%86%D0%B8%D0%BA%D0%BB%D0%BE%D0%BC-%D1%81%D1%82%D0%B0%D1%82%D0%B5%D0%B9-fast-web-f9715b21517f)

### Http requests with saving data to file

**Docker:**

Concurrency = 25, iterations=10

|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           | guzzle(multicurl)| 1.9195 | 2.3427 | 2.1016 |
|           | amphp| 1.7752 | 2.1213 | 1.9646 |
|           | reactphp (buzz-react)|  3.9908 | 4.1035 | 4.0339 |
| 100 queries|               |             |           |           |
|           |guzzle(multicurl)|5.0714 | 6.345 | 5.7387 |
|           | amphp   |   4.8419 | 5.7793 | 5.111 |
|           | reactphp (buzz-react)|   6.8191 | 6.8681 | 6.8394 |
| 500 queries|               |             |           |           |
|           |guzzle(multicurl)|22.7216 | 33.7153 | 27.6107 |
|           | amphp   | 24.045 | 35.9257 | 27.4128 |
|           | reactphp (buzz-react)|  21.6065 | 24.7808 | 23.6808 |
| 2000 queries|               |             |           |           |
|           |guzzle(multicurl)| 100.2036 | 139.9788 | 120.0329 |
|           | amphp| 109.8371 | 115.3629 | 112.8217 |
|           | reactphp (buzz-react)|88.6737 | 92.6061 | 91.0348 |
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)|  223.741 | 239.7989 | 232.8649 |
|           | amphp| 204.2293 | 227.4679 | 218.8668 |
|           | reactphp (buzz-react)| 173.8275 | 187.5087 | 182.6765 |



**Production server**


|Num queries |  Client      | Min Time   | Max Time   | Avg Time  |
|------------|--------------|------------|------------|-----------|
| 25 queries|               |             |           |           |
|           |guzzle(multicurl)|  - | - | - |
|           | amphp   |  - | - | - |
|           | reactphp|  - | - | - |
| 4000 queries|               |             |           |           |
|           |guzzle(multicurl)|-    |  -   | -   |
|           | amphp|  - | - | - |
|           | reactphp| - | - | - |
| 6000 queries|               |             |           |           |
|           |guzzle(multicurl)|- | - | - |
|           | amphp|  - | - | - |
|           | reactphp| - | - | - |
| 10000 queries (3 iterations)|               |             |           |           |
|           | amphp|  - | -   | -   |
|           | reactphp| -  | -  | -   |