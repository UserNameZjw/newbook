<?php
return [
    'default' => 'bqg',
    'bqg' => [
        'url'       =>  'http://www.xpsge.com',
        // 书籍搜索 search 参数
        'search'    =>  [
            'url'   =>  '/search.html',
            'param' =>  [ 'searchtype' => 'searchtype', 'searchkey' => 'searchkey' ],
            'xpath' =>  [ 'xpath' => "//*[@class='librarylist']/li" ,'each' => true ],
            'arr'   =>  [
                            'id'    => [
                                'xpath'     => [ 'xpath' => "//*[@class='novelname']" ,'each' => false ],
                                'moth'      => "attr",
                                'str'       => "href",
                                'fun'       => "getNumber"
                            ],

                            'title'         => [ 'xpath' => "//*[@class='novelname']" ,'each' => false ],     // 书籍名称
                            'author'        => [ 'xpath' => "//*[@class='info']/span[2]/a" ,'each' => false ],// 作者
                            'type'          => [ 'xpath' => "//*[@class='info']/span[3]/a" ,'each' => false ],// 类型

                            // 类型 url
                            'typeUrl'      => [
                                'xpath'     => [ 'xpath' =>  "//*[@class='info']/span[3]/a",'each' => false ],
                                'moth'      => "attr",
                                'str'       => "href",
                                'fun'       => [ 'name'  => "getLinkUrl", 'param' => ['index' => 2,'indexTow' => 0] ]
                            ],
                            'section'       => ['xpath'  =>  "//*[@class='last']/a" ,'each' => false ],  // 最新章节

                            // 最新章节 url
                            'sectionId'    => [
                                'xpath'     => ['xpath' =>  "//*[@class='last']/a" ,'each' => false ],
                                'moth'      => "attr",
                                'str'       => "href",
                                'fun'       => [ 'name' => "getLinkUrl", 'param' => ['index' => 2,'indexTow' => 0] ]
                            ]

            ]
        ],// 书籍搜索 search 参数结束

        // 书籍详情 details 参数
        'details'  => [
            'url'  => [
                'before'    => '/shu_',
                'dataId'   => 'id',
                'after'     => '/'
            ],
            'xpath' => [ 'xpath' => "//*[@class='w-left']" ,'each' => false ],
            'param' => [],
            'arr'   => [
                'title'     =>  [ 'xpath' => "//*[@class='header line']/h1" ,'each' => false ],          // 名称
                'author'    =>  [ 'xpath' => "//*[@class='novelinfo-l']/ul/li[1]/a" ,'each' => false ],  // 作者
                'intro'     =>  [ 'xpath' => "//*[@class='body novelintro ']" ,'each' => false ],        // 简介
                'uptime'    =>  [ 'xpath' => "//*[@class='novelinfo-l']/ul/li[7]" ,'each' => false ],
                // 二维组数 代表子元素
                // 相关作品
                'showreel'  =>  [
                                'xpath' => "//*[@class='coverlist clearfix']/li" ,
                                'each'  => true ,
                                'arr'   => [
                                    'title'   => [ 'xpath' => "//*[@class='name cut']" ,'each' => false ],
                                    'id'      => [
                                        'xpath'     => [ 'xpath' => "//*[@class='name cut']/a" ,'each' => false ],
                                        'moth'      => "attr",
                                        'str'       => "href",
                                        'fun'       => "getNumber"
                                    ]
                                ]
                ],// 相关作品结束

                // 最新章节
                'newSection'   =>  [
                                    'xpath' => "//*[@class='card mt20 ']/div[@class='body ']/ul[@class='dirlist three clearfix']/li" ,
                                    'each'  => true ,
                                    'arr'   => [
                                        'section'       => [ 'xpath' => "//*/a" ,'each' => false ],
                                        'sectionId'    => [
                                            'xpath'     => ['xpath'  =>  "//*/a" ,'each' => false ],
                                            'moth'      => "attr",
                                            'str'       => "href",
                                            'fun'       => [ 'name'  => "getLinkUrl", 'param' => ['index' => 2,'indexTow' => 0] ]
                                        ]
                                    ]
                ],  // 最新章节结束

                // 章节目录
                'list'          =>  [
                                    'xpath' => "//*[@class='card mt20 fulldir']/div[@class='body ']/ul[@class='dirlist three clearfix']/li" ,
                                    'each'  => true ,
                                    'arr'   => [
                                        'section'       => [ 'xpath' => "//*/a" ,'each' => false ],
                                        'sectionId'    => [
                                            'xpath'     => ['xpath' =>  "//*/a" ,'each' => false ],
                                            'moth'      => "attr",
                                            'str'       => "href",
                                            'fun'       => [ 'name' => "getLinkUrl", 'param' => ['index' => 2,'indexTow' => 0] ]
                                        ]
                                    ]
                ],  // 章节目录结束
            ]
        ],  // 书籍详情 details 结束

        // 书籍阅读 article
        'article'   => [
            'url'   => [
                'before'    => '/novelsearch/reader/transcode/siteid/',
                'dataId'    => 'id',
                'after'     => '/',
                'dataSectionId'    => 'section',
                'end'       => '/',
            ],
            'xpath' => [ 'xpath'   => "" ,'each' => false  ,'uni' => true ],
            'param' => [],
            'arr'   => [ 'content' => 'info' ] // 因为是 uni 所以接收的肯定是一个 对象形式的数据
        ],  // 书籍阅读 article 结束
        // ...
    ]

];
