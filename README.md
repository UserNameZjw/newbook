#### 项目起因
其实本人也喜欢看小说,奈何市面上的免费小说软件一直都是广告盈利模式,也一直有心思写一个简单的,自用的小软件,奈何前端实在是软肋,就一直搁浅,直到遇到了一个同道中人,他是前端,所以就出现了这个项目,本项目考虑比较简单,纯当自用。

#### 项目描述
一个基于 think-swoole + redis 的小项目.
功能介绍:
1. 书籍搜索,自动 Task 后台抓取所有显示书籍,写入队列,后台启动定时任务抓取,同时把搜索关键词MD5后作为 Key 存入 Redis ,有效期三个月,便于以后更新同关键词,本地书籍不足问题.
2. 书籍详情,如果本库没有此书籍,自动用此书籍名称模拟一次用户搜索,实现接口统一投递.
3. 书籍阅读,如果本库没有此章节,自动采集写入 Redis ,但是并不做书籍全本抓取.
4. 自动对本地缓存库内的书籍进行定时追更。

#### 项目框架
1. Thinkphp6 + Swoole 4.6.2 +think-swoole3 + Redis6 + Nginx
2. 支持可自定义的抓取规则，配置文件 config/book。
3. 使用go() 协程最大化的利用硬件性能。
4. 实现think-swoole 配置化 task 后台处理大批量采集录入
5. 实现think-swoole 定时任务执行基于Redis 实现的队列处理
6. 使用think-swoole 基于 Redis 连接池
7. 本项目没有接入 mysql 之类的关系型数据库储存，原因是，不做数据保留，仅此而已。


#### Redis 储存结构图
![Redis 储存结构图](https://www.hualigs.cn/image/603f03974616e.jpg)
___

#### 公用参数（每个API 都可传可不传）
| 参数        | 类型    | 示例      |是否必须   |  说明     |
| :-------    | :----:  | :----:    | :----:    | :----     |
| config      | String  |   bqg     |    否 [ 默认 bqg ]    | 书源标识   |


#### 书籍搜索

请求地址：/v1/api/search
请求类型：GET/POST

| 参数        | 类型    | 示例      |是否必须   |  说明     |
| :-------    | :----:  | :----:    | :----:    | :----     |
| searchtype  | String  | [ novelname/author ]  |    是     |书名或者作者|
| searchkey   | String  |   圣墟    |    是     | 用户输入  |

##### 示例请求
```
/v1/api/search?searchtype=novelname&searchkey=圣墟
// /v1/api/search?searchtype=[ 查询类型 ]&searchkey=[ 查询输入 ]
```

##### 返回结果示例：

```
{
    "code": true,
    "msg": "操作成功",
    "searchtype": "novelname",
    "searchkey": "圣墟",
    "list": [
        {
            "id": "2428",
            "title": "圣墟",
            "author": "辰东",
            "type": "玄幻",
            "typeUrl": "xuanhuan",
            "section": "第1618章 曾心怀天下的仙帝",
            "sectionUrl": "1640"
        },
        {
            "id": "23978",
            "title": "圣虚(圣墟)",
            "author": "辰东",
            "type": "其他",
            "typeUrl": "qita",
            "section": "第1405章 得见女帝",
            "sectionUrl": "1392"
        },
        ...
    ]
}
```

##### 返回结果说明：

| 参数          | 类型   | 说明      |
| :-------      | :----: | :----     | 
| code          | Bool   | false/true 判断是否获取成功|
| msg           | String |  返回信息 |
| searchtype    | String |  查询类型 [ 作者、书籍 ]   |
| searchkey     | String |  用户输入 |
| list          | Object |  数据列表 |

##### list 数据说明：

| 参数          | 类型   | 说明                 |
| :-------      | :----: | :----                | 
| id            | String | 书籍ID 查询详情传递  |
| title         | String | 书籍名称             |
| author        | String | 书籍作者             |
| type          | String | 书籍类型             |
| typeUrl       | String | 书籍类型url          |
| section       | String | 最新章节             |
| sectionUrl    | String | 章节ID 阅读时传递    |


---
#### 书籍详情

请求地址：/v1/api/details
请求方式：GET/POST

| 参数      | 类型    | 示例    |是否必须   |  说明 |
| :-------  | :----:  | :----:  | :----:    | :---- |
| id        | String  |   2428  |    是     |书籍id |

##### 示例请求
```
/v1/api/details?id=2428
// /v1/api/details?id=[ 书籍id ]
```

##### 返回结果示例：
```
{
    "code":true,
    "msg":"操作成功",
    "id":"2428",
    "title":"圣墟",
    "author":"辰东",
    "intro":"在破败中崛起，在寂灭中复苏……",
    "showreel":[
        {
            "title":"圣墟",
            "id":"2428"
        },
        {
            "title":"圣墟（圣虚）",
            "id":"5940"
        }, 
        ...
    ],
    "newSection":[
        {
            "section":"第1620章 仙帝献祭地",
            "sectionId":"1642"
        },
        {
            "section":"第1619章 以身填坑",
            "sectionId":"1641"
        },
        ...
    ],
    "list":[
        {
            "section":"第一章 沙漠中的彼岸花",
            "sectionId":"1"
        },
        {
            "section":"第二章 后文明时代",
            "sectionId":"2"
        },
        ...
    ]
}
```

##### 返回结果说明：

| 参数          | 类型   | 说明     |
| :-------      | :----: | :----    | 
| code          | Bool   | false/true 判断是否获取成功|
| msg           | String | 返回信息 |
| id            | String | 书籍ID 参数回传            |
| title         | String | 书籍名称 |
| author        | String | 书籍作者 |
| intro         | String | 书籍简介 |
| showreel      | Object | 相关书籍 |
| newSection    | Object | 最近更新 |
| list          | Object | 所有目录 |


##### showreel 数据说明：

| 参数      | 类型   | 说明     |
| :-------  | :----: | :----    | 
| id        | String | 书籍ID   |
| title     | String | 书籍名称 |


##### newSection 数据说明：

| 参数       | 类型   | 说明     |
| :-------   | :----: | :----    | 
| section    | String | 章节名称 |
| sectionUrl | String | 章节ID   |

##### list 数据说明：

| 参数       | 类型   | 说明     |
| :-------   | :----: | :----    | 
| section    | String | 章节名称 |
| sectionUrl | String | 章节ID   |


----

#### 章节阅读
请求地址：/v1/api/article
请求方式：GET/POST

| 参数      | 类型    | 示例    |是否必须   |  说明 |
| :-------  | :----:  | :----:  | :----:    | :---- |
| id        | String  |   2428  |    是     |书籍id |
| section   | String  |   1     |    是     |章节id |

##### 示例请求
```
/v1/api/article?id=2428&section=1
// /v1/api/article?id=[ 书籍id ]&section=[ 章节id ]
```

#### 返回结果示例
```
{
    "content":"大漠孤烟直，长河落日圆。...",
    "id":"2428",
    "section":"1",
    "code":true,
    "msg":"查询成功",
    "title":"第一章 沙漠中的彼岸花"
}
```
##### 返回结果说明：

| 参数          | 类型   | 说明     |
| :-------      | :----: | :----    | 
| code          | Bool   | false/true 判断是否获取成功|
| id            | String | 书籍ID 参数回传 |
| section       | String | 章节ID 参数回传 |
| msg           | String | 返回信息 |
| title         | String | 章节名称 |
