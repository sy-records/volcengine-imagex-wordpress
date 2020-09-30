## 使用方式

### composer引用
```
composer require ttvcloud/vcloud-sdk-php
```

### 地域Region设置
- 目前已开放三个地域设置，分别为
  ```
  - cn-north-1 (默认)
  - ap-singapore-1
  - us-east-1
  ```
- 默认为cn-north-1，如果需要调用其它地域服务，请在初始化函数getInstance中传入指定地域region，例如：
  ```
  $client = Vod::getInstance('us-east-1');
  ```
- 注意：IAM模块目前只开放cn-north-1区域

### AK/SK设置
- 在代码里显示调用VodService的方法setAccessKey/setSecretKey

- 在当前环境变量中分别设置 VCLOUD_ACCESSKEY="your ak"  VCLOUD_SECRETKEY = "your sk"

- json格式放在～/.vcloud/config中，格式为：{"ak":"your ak","sk":"your sk"}

以上优先级依次降低，建议在代码里显示设置，以便问题排查

### API

#### 上传

- 通过指定url地址上传

[uploadMediaByUrl](https://open.bytedance.com/docs/4/4652/)

- 服务端直接上传


上传视频包括 [applyUpload](https://open.bytedance.com/docs/4/2915/) 和 [commitUpload](https://open.bytedance.com/docs/4/2916/) 两步

上传封面图包括 [applyUpload](https://open.bytedance.com/docs/4/2915/) 和 [modifyVideoInfo](https://open.bytedance.com/docs/4/4367/) 两步


为方便用户使用，封装方法 uploadVideo 和 uploadPoster， 一步上传


#### 转码
[startTranscode](https://open.bytedance.com/docs/4/1670/)


#### 发布
[setVideoPublishStatus](https://open.bytedance.com/docs/4/4709/)


#### 播放
[getPlayInfo](https://open.bytedance.com/docs/4/2918/)

[getOriginVideoPlayInfo](https://open.bytedance.com/docs/4/11148/)

[getRedirectPlay](https://open.bytedance.com/docs/4/9205/)

#### 封面图
[getPosterUrl](https://open.bytedance.com/docs/4/5335/)

#### token相关
[getUploadAuthToken](https://open.bytedance.com/docs/4/6275/)

[getPlayAuthToken](https://open.bytedance.com/docs/4/6275/)

PS: 上述两个接口和 [getRedirectPlay](https://open.bytedance.com/docs/4/9205/) 接口中均含有 X-Amz-Expires 这个参数

关于这个参数的解释为：设置返回的playAuthToken或uploadToken或follow 302地址的有效期，目前服务端默认该参数为15min（900s），如果用户认为该有效期过长，可以传递该参数来控制过期时间
。


#### STS2鉴权

点播提供的 API ( default )

getVideoPlayAuth(array $vidList, array $streamTypeList, array $watermarkList)

vidList、streamTypeList、watermarkList 为3种资源，分别代表视频vid、stream type和水印三种资源，数组为空是代表允许访问所有资源。

默认的 action 为 vod::GetPlayInfo（不需手动设置）

默认过期时间为1小时，可以通过如下 API 自定义过期时间

getVideoPlayAuthWithExpiredTime(array $vidList, array $streamTypeList, array $watermarkList, int $expire)

示例代码：

```
$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$expire = 60; // 请求的签名有效期

echo "\nSTS2鉴权签名\n";
$space = "";
$response = $client->getVideoPlayAuthWithExpiredTime([], [], [], $expire);
echo json_encode($response);

echo "\nSTS2鉴权签名，过期时间默认1小时\n";
$vid = "";
$response = $client->getVideoPlayAuth([], [], []);
echo json_encode($response);
```

自定义 STS2 授权模式

```
// 第1步 创建 actions 和 resources
$actions = ['service:Method']; // eg: vod:GetPlayInfo
$resources = [];
// 其中每个 resource 格式类似 "trn:vod::*:video_id/%s"，若允许全部则用 * 替代，否则用实际字符串替代，本例可以填写实际的 vid
if (sizeof($vidList) == 0) {
    $resources[] = sprintf($ResourceVideoFormat, "*");
} else {
    foreach ($vidList as $vid) {
        $resources[] = sprintf($ResourceVideoFormat, $vid);
    }
}

// 第2步 创建 Statement,允许的 NewAllowStatement, 拒绝的 NewDenyStatement，并添加到 Policy 对应的 Statement 数组里，并创建 Policy
$statement = $this->newAllowStatement($actions, $resources);
$policy = [
    'Statement' => [$statement],
];

// 第3步 调用 signSts2 生成签名
$this->signSts2($policy, $expire);

```


#### 更多示例参见
example


## 封面图

1.GetDomainInfo 产品化对外域名调度接口，根据spaceName获取CDN域名（定期获取，本地缓存）

2.getPosterUrl 获取封面图地址

 包含四个参数，分别为：

1）space 空间名称

2）图片uri地址

3）降级域名及权重，形如['p1.test.com' => 10, 'p3.test.com' => 5]

4）option参数

- VOD_TPL_OBJ: 获取图片源文件，无参数

- VOD_TPL_NOOP: 获取压缩的原图，无参数(png为无损压缩，如果编码为png可能会变大)

- VOD_TPL_RESIZE: 仅下采样的等比缩略，需要参数宽高。如果某条边为0，则以另一条边进行等比缩略，否则以宽高比较短的来

- VOD_TPL_CENTER_CROP: 居中裁剪，需要参数宽高。居中裁剪尽量少的像素到指定的宽高比后缩略为指定的裁剪宽高，如果某条边为0，则使用原图的对应边的分辨率

- VOD_TPL_SMART_CROP: 智能裁剪，需要参数宽高。智能分析了图片内容，尽可能保留图片中想要保留的内容。

- VOD_TPL_SIG: 带签名鉴权的图片地址
