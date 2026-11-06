---
title: 门面方法对照
---

# 门面方法对照

1.x 的门面类在 2.0 全部移除，本页按类逐项列出旧方法与新入口；类映射总览与全局函数迁移见[API 对照](./api)，数字码见[数字码对照](./codes)。

## 一、`\fxapp\Base`

| 旧方法 | 新写法 |
| --- | --- |
| `load($file, $type)` | `File\Loader::load()`（副作用脚本）、`File\Loader::read()`（数据文件） |
| `lang($name, $vars, $lang)` | `Language::phrase($name, $locale, $vars, true)`（含首字母大写） |
| `langList($name, $vars, $lang)` | `Language::phrase($name, $locale, $vars)` |
| `langParse($name)` | `Language::phrase($name, $locale)` |
| `config(...$vars)` | `$kernel->config()->get()`、`Config\Store::set()` |
| `cookie(...$vars)` | `CookieJar::get()`、`CookieJar::set()` |
| `env(...$vars)` | `Config\Store`（`env` 命名空间） |
| `dump(...$vars)` | `Dump::write(...)` |
| `json($data, $type, $param)` | `Json::encode()`、`Json::decode()`；非 JSON 串按查询串解码用 `Encode::decodeAll()`；v1 对已编码串不再编码，v2 需先 `Type::isJson()` 判断 |
| `ipv4($data, $type)` | `Ip::toLong()`、`Ip::fromLong()` |
| `crypt($data, $type, $param)` | `Cipher::encrypt()`、`Cipher::decrypt()`；旧数据用 `DesCipher::decrypt()` |
| `throwable($message)` | 抛异常，由接入层处理 |
| `echo($message, $code, $data, $extend)` | `Envelope::success()`、`Envelope::failure()` |

## 二、`\fxapp\Client`

| 旧方法 | 新写法 |
| --- | --- |
| `ip($type = 0)` | `ClientInfo::ip()`；需要整型时用 `Ip::toLong()` |
| `browser($type = -1)` | `ClientInfo::browser(CaseFormat::Original)` |
| `system($type = -1)` | `ClientInfo::system(CaseFormat::Original)` |
| `param($param, $method)` | `Http\RequestInput::all()` / `query()` / `post()` / `body()` / `cli()`；按结构提取用 `only()` |

## 三、`\fxapp\Data`

| 旧方法 | 新写法 |
| --- | --- |
| `where($data, $param)` | `WhereBuilder`，见[数字码对照](./codes)第四节 |
| `whereSafe($value, &$method, $case)` | 由 `WhereBuilder` 绑定参数覆盖，不再需要字符串白名单 |
| `whereFilter($value, $method, $case)` | 由 `WhereBuilder` 绑定参数覆盖，同上 |
| `whereBuild($key, $value, $method)` | `WhereBuilder::eq()`、`in()`、`like()` 等 |
| `whereAssemble($data)` | `WhereBuilder::build()` |
| `whereMake($data, $type)` | `WhereBuilder::build()`；需要前缀时用 `Expression` 拼接 |
| `insert($table, $data)` | `StatementBuilder::insert()` |
| `update($table, $data, $param)` | `StatementBuilder::update()` |
| `updateContrast($data_new, $data_old)` | `Diff::make()`，未变化时抛 `SameDataException` |
| `storeUpperLevel(...)` | `Data\Hierarchy::ancestors($list, $id, $idKey, $parentKey)` |
| `storeLowerLevel(...)` | `Data\Hierarchy::descendants($list, $id, $idKey, $parentKey)` |
| `fieldDistance(...)` | `Field::distance()` / `Sql\Expression::distance()` |
| `fieldInitial($field)` | `Field::initial()` / `Sql\Expression::initial()` |
| `fieldDivision(...)` | `Field::division()` / `Sql\Expression::division()` |
| `fieldJson($field, $replace, $param)` | `Field::json($column, $path, FieldJsonMode::Quoted)` |
| `fieldText($field, $replace, $param)` | `Field::text($column, $replace, FieldTextMode::Keep)` |
| `fieldDate($field, $replace, $param)` | `Field::date($column, DateGranularity::DateTime, $replace)` |
| `fieldAlias($data, $alias, $delimiter)` | `Field::alias($data, $alias, $delimiter)` |
| `fieldEscape($data, $delimiter)` | `Field::escape($data, $delimiter)` |
| `htmlFilter($data, $flags)` | `Html::escape($data)` |
| `htmlRemove($data, $flags)` | `Html::strip($data, $allowed)` |
| `listToTree($list, ...)` | `Tree::fromList($list, $rootId, $idKey, $parentKey, $childKey)` |
| `paramRepair($param)` | `Data\Param::repair($rows)` |
| `paramFilter($param)` | `Data\Param::filter($param)` |
| `paramEmpty($param, $type)` | `Data\Param::missing($param, NullPolicy::Blank)` |
| `paramExist($param)` | `[] === Data\Param::missing($param, NullPolicy::StrictNull)` |

## 四、`\fxapp\File`

| 旧方法 | 新写法 |
| --- | --- |
| `formatSize($size)` | `Formatter::size($bytes)` |
| `getList($path, $ext, $limit)` | `Filesystem::findFiles($directory, $extensions, $maxDepth)` |
| `moveDirectory($oldpath, $newpath)` | `Filesystem::moveDirectory()`（整目录移动，不再扁平合并内容） |
| `deleteDirectory($path)` | `Filesystem::deleteDirectory()` |
| `getMediaInfo($var, $type)` | `VideoInfo::inspect()` 取视频元信息；类型互查用 `MediaInfo::mime()`、`MediaInfo::extension()` |

## 五、`\fxapp\Math`

| 旧方法 | 新写法 |
| --- | --- |
| `rand($length, $type)` | 类型 0：`Random::string($length, RandomCharset::Alnum, RandomCharset::Alpha)`；类型 1：`Random::string($length, RandomCharset::Numeric)` |

## 六、`\fxapp\Param`

| 旧方法 | 新写法 |
| --- | --- |
| `init()` | `Application\Cli::parse()` + `Bootstrap::withCliArguments()` |
| `define($param, $mode)` | `Shape` 链式组合，见[数字码对照](./codes)第二节 |
| `array($param, $limit, $filter)` | `Data\Cast::toArray($param, $limit)` |
| `object($param, $limit, $filter)` | `Data\Cast::emptyToObject($param, $limit)` |
| `pick($base, $data)` | `Picker::pick()` |
| `append(...$args)` | `Merger::append()` |
| `merge($limit, $cover, $type, ...$args)` | `Merger::merge()`、`Merger::mergeKeepBlank()` |
| `cover($args, $limit, $cover, $type)` | `Merger::merge()` |
| `url($data)` | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| `json($var, $type, $param)` | `Json::encode()`、`Json::decode($value, $associative)`；非 JSON 串按查询串解码用 `Encode::decodeAll()` |

## 七、`\fxapp\Safe`

| 旧方法 | 新写法 |
| --- | --- |
| `crypt($data, $type, $param)` | 新数据用 `Cipher::encrypt()` / `decrypt()`；旧数据用 `DesCipher::encrypt()` / `decrypt()` |
| `md5($data, $type)` | `Hash::md5($data, HashLength::Full32)` |
| `token($data, $type)` | 新令牌用 `Token::make()` / `verify()`；旧令牌用 `DesToken::encode()` / `decode()` |
| `rsapri($data, $type, $param)` | 私钥加密用 `Rsa::privateEncrypt()`；签名与解密用 `Rsa::sign()` / `decrypt()` |
| `rsapub($data, $type, $param)` | 公钥解密用 `Rsa::publicDecrypt()`；验签与加密用 `Rsa::verify()` / `encrypt()` |
| `rsapem($data)` | `Rsa::normalizePem()` |

## 八、`\fxapp\Server`

| 旧方法 | 新写法 |
| --- | --- |
| `echo()` | `Envelope::success()` 的模板等价物 |
| `ip()` | `HttpClient\Curl` + `Json` 组合获取公网地址 |
| `system($type)` | PHP 原生 `PHP_OS_FAMILY` |
| `format($data, $type)` | `Envelope::toArray()`、`JsonFormatter::format()` |
| `env()` | `Application\Cli::serverDefaults()`、`RequestContext::fromGlobals()` |
| `branch()` | `Http\BranchContext::resolve()`，分支配置由调用方写入 `Config\Store` |

## 九、`\fxapp\Service`

| 旧方法 | 新写法 |
| --- | --- |
| `http($url, $data, $header, $method, $option)` | `HttpClient\Curl::request()`（URL 级）；需要 PSR-18 时用 `HttpClient\Client::send()` |
| `httpDown($url, $file)` | `HttpClient\Curl::download()` |

## 十、`\fxapp\Text`

| 旧方法 | 新写法 |
| --- | --- |
| `uuid($mode, $param)` | `Uuid::make(UuidMode, $seed, UuidStyle)`；格式化现有串用 `Uuid::format()` |
| `implode($delimiter, $data, $explode, $unique)` | `Str::implode($glue, $values, $unique)` |
| `explode($delimiter, $data, $unique, $limit)` | `Str::explode($separator, $value, $unique)` |
| `splice($string, $value, $delimiter)` | `Str::splice($value, $append, $glue)`；需要无条件追加时传 `$unique = false` |
| `strlen($string, $start, $end)` | `Str::isLength($value, $start, $end)` |
| `letters($var, $in_charset, $out_charset)` | `Encode::letters($value, $inCharset, $outCharset)` |
| `letter($var, $in_charset, $out_charset)` | `Encode::letter($value, $inCharset, $outCharset)` |
| `timeRange($time, $start, $end)` | `Time::range($time, TimeRangeOptions::make(...), ...)` |
| `timeChange($time, $type, $format)` | `Time::change($value, TimeOutput::Timestamp / Text, $format)` |
| `timeMilli($mtime)` | `Time::milli()`、`Time::milliFormat()`、`Time::milliParse()` |
| `timeFormat($time, $param)` | `Time::duration($seconds, $labels, $zero, TimeUnit, $before)`、`Time::decompose()` |
| `strEncode($data)` | `Encode::encodeAll($data)` |
| `strEncodeMerge($data)` | `Encode::encodeAll($data)`（递归辅助已合并） |
| `strDecode($data)` | `Encode::decodeAll($data)` |
| `strDecodeMerge($data)` | `Encode::decodeAll($data)`（递归辅助已合并） |
| `ipv4($data, $type)` | `Ip::toLong()`、`Ip::fromLong()` |
| `convert($data, $type)` | `Encode::convert($value, EncodeConvert::ToHex / FromHex)` |
| `throwable($th, $type, $param)` | 改为抛异常，由接入层映射为信封 |

## 十一、`\fxapp\View`

| 旧方法 | 新写法 |
| --- | --- |
| `echo($data, $type)` | `JsonFormatter::format()`、`XmlFormatter::format()`（按类型序列化） |
