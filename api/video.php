<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php 

ini_set('user_agent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.82 Safari/537.36');
function xmlToArray($xml, $options = array()) {
    $defaults = array(
        'namespaceSeparator' => ':',//you may want this to be something other than a colon
        'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
        'alwaysArray' => array(),   //array of xml tag names which should always become arrays
        'autoArray' => true,        //only create arrays for tags which appear more than once
        'textContent' => '$',       //key used for the text content of elements
        'autoText' => true,         //skip textContent key if node has no attributes or child nodes
        'keySearch' => false,       //optional search and replace on tag and attribute names
        'keyReplace' => false       //replace values for above search values (as passed to str_replace())
    );
    $options = array_merge($defaults, $options);
    $namespaces = $xml->getDocNamespaces();
    $namespaces[''] = null; //add base (empty) namespace
 
    //get attributes from all namespaces
    $attributesArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
            //replace characters in attribute name
            if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
            $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
            $attributesArray[$attributeKey] = (string)$attribute;
        }
    }
 
    //get child nodes from all namespaces
    $tagsArray = array();
    foreach ($namespaces as $prefix => $namespace) {
        foreach ($xml->children($namespace) as $childXml) {
            //recurse into child nodes
            $childArray = xmlToArray($childXml, $options);
            // list($childTagName, $childProperties) = each($childArray); //旧版写法
            foreach($childArray as $childTagName => $childProperties);
 
            //replace characters in tag name
            if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
            //add namespace prefix, if any
            if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
 
            if (!isset($tagsArray[$childTagName])) {
                //only entry with this key
                //test if tags of this type should always be arrays, no matter the element count
                $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                        ? array($childProperties) : $childProperties;
            } elseif (
                is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                === range(0, count($tagsArray[$childTagName]) - 1)
            ) {
                //key already exists and is integer indexed array
                $tagsArray[$childTagName][] = $childProperties;
            } else {
                //key exists so convert to integer indexed array with previous value in position 0
                $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
            }
        }
    }
 
    //get text content of node
    $textContentArray = array();
    $plainText = trim((string)$xml);
    if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;
 
    //stick it all together
    $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
 
    //return node as array
    return array(
        $xml->getName() => $propertiesArray
    );
}
function curl_get($url, $gzip=false){
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
if($gzip) curl_setopt($curl, CURLOPT_ENCODING, "gzip"); // 关键在这里
$content = curl_exec($curl);
curl_close($curl);
return $content;
}
function getUrl() {
$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
if($_SERVER['SERVER_PORT'] != '80') {
    $url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
} else {
    $url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
}
$url = str_replace("http://127.0.0.1:8000","https://v.maware.cc",$url);
return $url;
}
function changeURLParam($url, $name, $value)
{
    $reg = "/([\?|&]" . $name . "=)[^&]*/i";
    $value = urlencode(trim($value));
    if (empty($value)) {
        return preg_replace($reg, '', $url);
    } else {
        if (preg_match($reg, $url)) {
            return preg_replace($reg, '${1}${2}' . $value, $url);
        } else {
            return $url . (strpos($url, '?') === false ? '?' : '&') . $name . '=' . $value;
        }
    }
}
function danmakuCmp($a,$b){
    return ((float)explode(',',$a["@p"])[0]) > ((float)explode(',',$b["@p"])[0]);
}
function getDanmaku($danmaku_url){
    $xml_danmaku = curl_get($danmaku_url, true);
    $xmlNode = simplexml_load_string($xml_danmaku);
    $danmaku_array_resource = xmlToArray($xmlNode);
    $danmaku_list = array_slice($danmaku_array_resource["i"]["d"], 0, 500);
    usort($danmaku_list, "danmakuCmp");
    $danmaku_json_string = "[\n";
    $iop = 0;
    foreach ($danmaku_list as $d){
		$dan_msg = explode(',',$d["@p"]);
		$dan_time = $dan_msg[0];
		$dan_color = $dan_msg[3];
		$dan_text = str_replace("\\","/",str_replace("'",'"',$d["$"]));
		$dan_type = "scroll";
		if($dan_msg[1]=='1'||$dan_msg[1]=='2'||$dan_msg[1]=='3'){
		    $dan_type = "scroll";
		}else if($dan_msg[1]=='4'){
		    $dan_type = "bottom";
		}else if($dan_msg[1]=='5'){
		    $dan_type = "top";
		}else{
		    continue;
		}
		if($iop==0){
		    $danmaku_json_string .= "{ time: ".$dan_time.", text: '".$dan_text."', color: '#".dechex($dan_color)."', type: '".$dan_type."'}";
		    $iop = 1;
		}else{
		    $danmaku_json_string .= ",\n{ time: ".$dan_time.", text: '".$dan_text."', color: '#".dechex($dan_color)."', type: '".$dan_type."'}";
		}
	}
    $danmaku_json_string .= "\n]";
    return $danmaku_json_string;
}

if($_GET['id']!=''&&$_GET['type']!=''&&$_GET['url']==''){
    $node_json = json_decode(file_get_contents("https://raw.githubusercontent.com/AnimeCDN/AnimeCDN/master/node/node.json"), true);
    $mix_json = json_decode(file_get_contents("https://raw.githubusercontent.com/AnimeCDN/AnimeCDN/master/index.json"), true);
    $json_url = $mix_json[$_GET['type']][((int)$_GET['id'])-1]["url"];
    $json = json_decode(file_get_contents($json_url), true);
    // header("content-type:application/json");
    // var_dump($json["tags"]);
}else if($_GET['id']==''&&$_GET['type']==''&&$_GET['url']!=''){
    $node_json = json_decode(file_get_contents("https://raw.githubusercontent.com/AnimeCDN/AnimeCDN/master/node/node.json"), true);
    $json = json_decode(file_get_contents($_GET['url']), true);
}else{
    Header("Location: https://v.maware.cc/api/video?type=video&id=1"); 
    exit(0);
}

if($_GET['clar']!=''&&$_GET['p']!=''){
    $initial_url = $json["parts"][$_GET['clar']][((int)$_GET['p'])-1]["url"];
    if($_GET['proxy']!=''){
        $initial_url = str_replace("raw.githubusercontent.com", $_GET['proxy'], $initial_url);
    }
    if($json["danmaku"] != ''){
        if($_GET['type']=='anime'){
            $season_id = json_decode(curl_get("https://api.bilibili.com/pgc/review/user?media_id=".str_replace("md","",$json["danmaku"])),true)["result"]["media"]["season_id"];
            $cid = json_decode(curl_get("https://api.bilibili.com/pgc/web/season/section?season_id=".$season_id),true)["result"]["main_section"]["episodes"][((int)$_GET['p'])-1]["cid"];
            $initial_danmaku = getDanmaku("https://comment.bilibili.com/".$cid.".xml");
        }else{
            $cid = json_decode(curl_get("https://api.bilibili.com/x/player/pagelist?bvid=".$json["danmaku"]."&jsonp=jsonp"),true)["data"][((int)$_GET['p'])-1]["cid"];
            $initial_danmaku = getDanmaku("https://comment.bilibili.com/".$cid.".xml");
        }
    }else if($_GET['danmaku'] != ''){
        $initial_danmaku = getDanmaku($_GET['danmaku']);
    }
    $initial_name = $json["name"]."-"."第".$_GET['p']."集-".$json["parts"][$_GET['clar']][((int)$_GET['p'])-1]["title"]."-".$_GET['clar'];
}else{
    $initial_url = $json["parts"][$json["clarity"][0]][0]["url"];
    if($_GET['proxy']!=''){
        $initial_url = str_replace("raw.githubusercontent.com", $_GET['proxy'], $initial_url);
    }
    if($json["danmaku"] != ''){
        if($_GET['type']=='anime'){
            $season_id = json_decode(curl_get("https://api.bilibili.com/pgc/review/user?media_id=".str_replace("md","",$json["danmaku"])),true)["result"]["media"]["season_id"];
            $cid = json_decode(curl_get("https://api.bilibili.com/pgc/web/season/section?season_id=".$season_id),true)["result"]["main_section"]["episodes"][0]["cid"];
            $initial_danmaku = getDanmaku("https://comment.bilibili.com/".$cid.".xml");
        }else{
            $cid = json_decode(curl_get("https://api.bilibili.com/x/player/pagelist?bvid=".$json["danmaku"]."&jsonp=jsonp"),true)["data"][0]["cid"];
            $initial_danmaku = getDanmaku("https://comment.bilibili.com/".$cid.".xml");
        }
    }else if($_GET['danmaku'] != ''){
        $initial_danmaku = getDanmaku($_GET['danmaku']);
    }
    $initial_name = $json["name"]."-"."第1集-".$json["parts"][$json["clarity"][0]][0]["title"]."-".$json["clarity"][0];
}

$waline_page_url = changeURLParam(getUrl(),"clar","");
$waline_page_url = changeURLParam($waline_page_url,"p","");
$waline_page_url = changeURLParam($waline_page_url,"type","");
$waline_page_url = changeURLParam($waline_page_url,"proxy","");
$waline_page_url = changeURLParam($waline_page_url,"information",$json["name"]);

if($initial_danmaku == ""){
    $initial_danmaku = "[{ time: 1, text: '当前资源没有弹幕哦~', color: '#FFFFFF', type: 'top'}]";
}

?>
<title><?php echo $initial_name." - Maware"; ?></title>
<meta name="viewport" content="initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
<meta name="renderer" content="webkit" />
<meta http-equiv="Cache-Control" content="no-siteapp" />
<meta http-equiv="Cache-Control" content="no-transform" />
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<link rel="stylesheet" type="text/css" href="https://v.maware.cc/video.css" />
<link rel="icon" type="image/png" href="https://cdn.maware.cc/assets/images/favicon.ico">
<script src="https://cdn.jsdelivr.net/npm/nplayer@latest/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@waline/client/dist/Waline.min.js"></script>
<script src="https://unpkg.com/@nplayer/danmaku@latest/dist/index.min.js"></script>
</head>
<body class="fed-min-width">
    
<div class="fed-head-info fed-back-whits fed-min-width fed-box-shadow">
	<div class="fed-part-case">
		<div class="fed-navs-info">
			<ul class="fed-menu-info">
				<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-md-block" href="https://maware.cc/"><h2>Maware</h2></a>
			    </li>
				<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-md-block" href="https://maware.cc/">首页</a>
				</li>
								<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-md-block" href="https://maware.cc/categories">分类</a>
				</li>
								<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-lg-block" href="https://maware.cc/links">节点</a>
				</li>
								<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-lg-block" href="https://github.com/AnimeCDN/AnimeCDN/issues/new?assignees=&labels=%E8%B5%84%E6%BA%90%E6%8F%90%E4%BA%A4&template=commit.yml">提交</a>
				</li>
								<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-lg-block" href="https://github.com/AnimeCDN/AnimeCDN/issues/new?assignees=&labels=%E8%B5%84%E6%BA%90%E4%B8%BE%E6%8A%A5&template=report.yml">举报</a>
				</li>
								<li class="fed-pull-left">
					<a class="fed-menu-title fed-show-kind fed-font-xvi fed-hide fed-show-md-block" href="https://maware.cc/doc-Maware">文档</a>
				</li>
			</ul>
		</div>
	</div>
</div>

<div class="fed-main-info fed-min-width">
<div class="fed-part-case">
<div class="fed-play-info fed-part-rows fed-back-whits fed-marg-top">
	<div class="fed-play-player fed-rage-head fed-part-rows fed-back-black" style="padding-top:56.25%">
		<style type="text/css">@media(max-width:47.9375rem){.fed-play-player{padding-top:56.25%!important}}</style>
			<div id="fed-play-iframe" class="fed-play-iframe fed-part-full"></div>
	</div>
	<div class="fed-play-title fed-part-rows">
				<ul class="fed-play-boxs fed-padding fed-part-rows fed-col-xs12 fed-col-md6">
			<li class="fed-padding fed-col-xs5">
				<span class="fed-play-text fed-visible fed-font-xvi fed-part-eone"><?php echo $initial_name; ?></span>
			</li>
		</ul>
	</div>
</div>
<div class="fed-tabs-info  fed-rage-foot fed-part-rows fed-part-layout fed-back-whits fed-play-data" data-name="<?php echo $json["name"]; ?>">
	<ul class="fed-list-head fed-part-rows fed-padding">
				<li class="fed-tabs-btns fed-part-curs fed-font-xvi fed-mart-v" href="javascript:;">剧情简介</li>
	</ul>
	<div class="fed-tabs-boxs">
		<div class="fed-tabs-item fed-drop-info fed-visible">
		    <div class="fed-tabs-item fed-hidden fed-show" style="display: block;">
						<div class="fed-col-xs12 fed-col-sm8 fed-col-md9">
				<dl class="fed-deta-info fed-margin fed-part-rows fed-part-over">
	<dt class="fed-deta-images fed-list-info fed-col-xs3">
		<a class="fed-list-pics fed-lazy fed-part-2by3" target="_blank" href="<?php echo $json["blog"]; ?>" data-original="<?php echo $json["pic"]; ?>" style="background-image: url(&quot;<?php echo $json["pic"]; ?>&quot;); display: block;">
			<span class="fed-list-play fed-hide-xs"></span>
						<span class=" "></span>
						<span class="fed-list-score fed-font-xii fed-back-green"><?php echo $json["update-statement"]; ?></span>
		</a>
	</dt>
	<dd class="fed-deta-content fed-col-xs7 fed-col-sm8 fed-col-md10 ">
		<h1 class="fed-part-eone fed-font-xvi"><a target="_blank" href="<?php echo $json["blog"]; ?>"><?php echo $json["name"]; ?></a></h1>
		<ul class="fed-part-rows">
			<li class="fed-col-xs6 fed-col-md3 fed-part-eone"><span class="fed-text-muted">标签：</span>
			
			<?php
			foreach ($json["tags"] as $t){
			    echo "<a href=\"https://maware.cc/tags/".urlencode($t)."/\" target=\"_blank\">".$t."</a>&nbsp;";
			}
			?>
			
			</li>
			<li class="fed-col-xs12 fed-hide fed-show-md-block">
				<div class="fed-part-esan">
					<span class="fed-text-muted">简介：</span><?php echo $json["introduction"]; ?></div>
			</li>
		</ul>
	</dd>
	<dd class="fed-deta-button fed-col-xs7 fed-col-sm8 fed-part-rows">
				<a class="fed-deta-play fed-rims-info fed-btns-info fed-btns-green fed-col-xs4" target="_blank" href="<?php echo $json["blog"]; ?>">查看详情</a>
			</dd>
</dl>

			</div>
						<p class="fed-padding fed-part-both fed-text-muted"><?php echo $json["introduction"]; ?></p>
		</div>
		</div>
				<div class="fed-tabs-item fed-hidden">
						<div class="fed-col-xs12 fed-col-sm8 fed-col-md9">
				<dl class="fed-deta-info fed-margin fed-part-rows fed-part-over">
	<dt class="fed-deta-images fed-list-info fed-col-xs3">
		<a class="fed-list-pics fed-lazy fed-part-2by3" target="_blank" href="<?php echo $json["blog"]; ?>" data-original="<?php echo $json["pic"]; ?>" style="background-image: url(<?php echo $json["pic"]; ?>);">
			<span class="fed-list-play fed-hide-xs"></span>
						<span class=" "></span>
						<span class="fed-list-score fed-font-xii fed-back-green"><?php echo $json["update-statement"]; ?></span>
		</a>
	</dt>
	<dd class="fed-deta-content fed-col-xs7 fed-col-sm8 fed-col-md10 ">
		<h1 class="fed-part-eone fed-font-xvi"><a target="_blank" href="<?php echo $json["blog"]; ?>"><?php echo $json["name"]; ?></a></h1>
		<ul class="fed-part-rows">
			<li class="fed-col-xs6 fed-col-md3 fed-part-eone"><span class="fed-text-muted">标签：</span>
			
			<?php
			foreach ($json["tags"] as $t){
			    echo "<a href=\"https://maware.cc/tags/".urlencode($t)."/\" target=\"_blank\">".$t."</a>&nbsp;";
			}
			?>
			
			</li>
		</ul>
	</dd>
	<dd class="fed-deta-button fed-col-xs7 fed-col-sm8 fed-part-rows">
				<a class="fed-deta-play fed-rims-info fed-btns-info fed-btns-green fed-col-xs4" target="_blank" href="<?php echo $json["blog"]; ?>">查看详情</a>
			</dd>
            </dl>
			</div>
			<p class="fed-padding fed-part-both fed-text-muted"><?php echo $json["introduction"]; ?></p>
	    </div>
	</div>
</div>
<div class="fed-tabs-info  fed-rage-foot fed-part-rows fed-part-layout fed-back-whits fed-play-data" data-name="<?php echo $json["name"]; ?>">

    <?php
    foreach ($json["clarity"] as $clar){
        echo "<div class=\"fed-play-item fed-drop-item fed-visible\"><ul class=\"fed-drop-head fed-padding fed-part-rows\"><li class=\"fed-padding fed-col-xs4 fed-part-eone fed-font-xvi\">清晰度-".$clar."</li></ul></div><ul class=\"fed-part-rows\">";
		foreach ($json["parts"][$clar] as $part){
		    $now_url = changeURLParam(getUrl(),"clar",$clar);
		    $now_url = changeURLParam($now_url,"p",(string)$part["part"]);
		    echo "<li class=\"fed-padding fed-col-xs3 fed-col-md2 fed-col-lg1\"><a class=\"fed-btns-info fed-rims-info fed-part-eone fed-btns-green\" href=\"".$now_url."\">第".(string)$part["part"]."集</a>";
		}
		echo "</ul>";
	}
    ?>

 <!--   <div class="fed-play-item fed-drop-item fed-visible">-->
	<!--	<ul class="fed-drop-head fed-padding fed-part-rows">-->
	<!--		<li class="fed-padding fed-col-xs4 fed-part-eone fed-font-xvi">播放集数</li>-->
	<!--	</ul>-->
	<!--</div>-->
 <!--   <ul class="fed-part-rows">-->
	<!--	<li class="fed-padding fed-col-xs3 fed-col-md2 fed-col-lg1">-->
	<!--		<a class="fed-btns-info fed-rims-info fed-part-eone fed-btns-green" href="{[播放地址]}">第01集</a>-->
	<!--	</li>-->
	<!--</ul>-->
</div>
<div class="fed-tabs-info  fed-rage-foot fed-part-rows fed-part-layout fed-back-whits fed-play-data" data-name="节点列表">
    <div class="fed-play-item fed-drop-item fed-visible">
		<ul class="fed-drop-head fed-padding fed-part-rows">
			<li class="fed-padding fed-col-xs4 fed-part-eone fed-font-xvi">节点列表</li>
		</ul>
	</div>
	<ul class="fed-part-rows">
	<li class="fed-padding fed-col-xs3 fed-col-md2 fed-col-lg1"><a class="fed-btns-info fed-rims-info fed-part-eone fed-btns-green" href="<?php echo changeURLParam(getUrl(),"proxy",""); ?>">不使用节点</a></li>
    <?php
    foreach ($node_json as $node){
        $now_node_url = changeURLParam(getUrl(),"proxy",$node["domain"]);
        echo "<li class=\"fed-padding fed-col-xs3 fed-col-md2 fed-col-lg1\"><a class=\"fed-btns-info fed-rims-info fed-part-eone fed-btns-green\" href=\"".$now_node_url."\">".$node['name']."</a></li>";
	}
    ?>
    </ul>
</div>
<div class="fed-tabs-info  fed-rage-foot fed-part-rows fed-part-layout fed-back-whits fed-play-data" data-name="评论区">
    <div class="fed-play-item fed-drop-item fed-visible">
		<ul class="fed-drop-head fed-padding fed-part-rows">
			<li class="fed-padding fed-col-xs4 fed-part-eone fed-font-xvi">评论区</li>
		</ul>
	</div>
	<div id="waline"></div>
</div>
</div>
</div>

<div class="fed-foot-info fed-part-layout fed-back-whits">
	<div class="fed-part-case">
		<p class="fed-text-center fed-text-black"></p>
		<p class="fed-text-center fed-text-black fed-hide"></p>
			 <div class="masked">
    <h4><p class="fed-text-center fed-text-black">&nbsp;&nbsp;&nbsp;免责说明：本站所有视频均来自互联网收集而来，版权归原创者所有，如果侵犯了你的权益，请通过 <a target="_blank" href="https://github.com/AnimeCDN/AnimeCDN/issues/new?assignees=&labels=%E8%B5%84%E6%BA%90%E4%B8%BE%E6%8A%A5&template=report.yml"> AnimeCDN资源举报页面 </a>进行举报，我们会及时删除侵权内容，谢谢合作。</p>
    <p class="fed-text-center fed-text-black">&nbsp;&nbsp;&nbsp;如果您有热爱的番剧资源，并想提交给本站，请通过 <a target="_blank" href="https://github.com/AnimeCDN/AnimeCDN/issues/new?assignees=&labels=%E8%B5%84%E6%BA%90%E6%8F%90%E4%BA%A4&template=commit.yml"> AnimeCDN资源提交页面 </a>进行提交，我们会及时审核，感谢您的支持！</p>
		        <p class="fed-text-center fed-text-black">&nbsp;&nbsp;&nbsp;本站由<a target="_blank" href="https://github.com/AnimeCDN/AnimeCDN"> AnimeCDN </a>提供服务。</p>
		<p class="fed-text-center fed-text-black">&copy;&nbsp;2022&nbsp;<a class="fed-font-xiv" href="" target="_blank">Maware</a></p>
   </h4>
</div>
</div>
</div>
<script>

    function createIcon(html, noCls) {
        const div = document.createElement('div')
        div.innerHTML = html;
        if (!noCls) div.classList.add('nplayer_icon')
        return (cls) => { if (cls) { div.classList.add(cls) } return div };
    }

    var play = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 28 28" width="28" height="28" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: translate3d(0px, 0px, 0px);"><defs><clipPath id="__lottie_element_153"><rect width="28" height="28" x="0" y="0"></rect></clipPath></defs><g clip-path="url(#__lottie_element_153)"><g transform="matrix(0.2747209668159485,0,0,0.2747209668159485,20.91462516784668,14.090660095214844)" opacity="0.019381329530385613" style="display: none;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-5.484000205993652,-10 C-7.953000068664551,-10 -8,-7.984000205993652 -8,-7.984000205993652 C-8,-7.984000205993652 -8.008000373840332,7.984000205993652 -8.008000373840332,7.984000205993652 C-8.008000373840332,7.984000205993652 -7.984000205993652,9.991999626159668 -5.5,9.991999626159668 C-3.0160000324249268,9.991999626159668 -3.003999948501587,7.995999813079834 -3.003999948501587,7.995999813079834 C-3.003999948501587,7.995999813079834 -2.9839999675750732,-8 -2.9839999675750732,-8 C-2.9839999675750732,-8 -3.015000104904175,-10 -5.484000205993652,-10z"></path><path stroke-linecap="butt" stroke-linejoin="miter" fill-opacity="0" stroke-miterlimit="4" stroke="rgb(255,255,255)" stroke-opacity="1" stroke-width="0" d=" M-5.484000205993652,-10 C-7.953000068664551,-10 -8,-7.984000205993652 -8,-7.984000205993652 C-8,-7.984000205993652 -8.008000373840332,7.984000205993652 -8.008000373840332,7.984000205993652 C-8.008000373840332,7.984000205993652 -7.984000205993652,9.991999626159668 -5.5,9.991999626159668 C-3.0160000324249268,9.991999626159668 -3.003999948501587,7.995999813079834 -3.003999948501587,7.995999813079834 C-3.003999948501587,7.995999813079834 -2.9839999675750732,-8 -2.9839999675750732,-8 C-2.9839999675750732,-8 -3.015000104904175,-10 -5.484000205993652,-10z"></path></g></g><g transform="matrix(0.7176067233085632,0,0,0.7176067233085632,22.7425537109375,14)" opacity="0.0618618270600345" style="display: none;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-5.484000205993652,-10 C-7.953000068664551,-10 -8,-7.984000205993652 -8,-7.984000205993652 C-8,-7.984000205993652 -8.008000373840332,7.984000205993652 -8.008000373840332,7.984000205993652 C-8.008000373840332,7.984000205993652 -7.984000205993652,9.991999626159668 -5.5,9.991999626159668 C-3.0160000324249268,9.991999626159668 -3.003999948501587,7.995999813079834 -3.003999948501587,7.995999813079834 C-3.003999948501587,7.995999813079834 -2.9839999675750732,-8 -2.9839999675750732,-8 C-2.9839999675750732,-8 -3.015000104904175,-10 -5.484000205993652,-10z"></path><path stroke-linecap="butt" stroke-linejoin="miter" fill-opacity="0" stroke-miterlimit="4" stroke="rgb(255,255,255)" stroke-opacity="1" stroke-width="0" d=" M-5.484000205993652,-10 C-7.953000068664551,-10 -8,-7.984000205993652 -8,-7.984000205993652 C-8,-7.984000205993652 -8.008000373840332,7.984000205993652 -8.008000373840332,7.984000205993652 C-8.008000373840332,7.984000205993652 -7.984000205993652,9.991999626159668 -5.5,9.991999626159668 C-3.0160000324249268,9.991999626159668 -3.003999948501587,7.995999813079834 -3.003999948501587,7.995999813079834 C-3.003999948501587,7.995999813079834 -2.9839999675750732,-8 -2.9839999675750732,-8 C-2.9839999675750732,-8 -3.015000104904175,-10 -5.484000205993652,-10z"></path></g></g><g style="display: block;" transform="matrix(1,0,0,1,14,14)" opacity="1"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d="M0 0"></path><path stroke-linecap="butt" stroke-linejoin="miter" fill-opacity="0" stroke-miterlimit="4" stroke="rgb(255,255,255)" stroke-opacity="1" stroke-width="0" d="M0 0"></path></g><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-7.031000137329102,-10.875 C-7.031000137329102,-10.875 -8.32800006866455,-11.25 -9.42199993133545,-10.468999862670898 C-10.109999656677246,-9.906999588012695 -10,-7.992000102996826 -10,-7.992000102996826 C-10,-7.992000102996826 -10,8.015999794006348 -10,8.015999794006348 C-10,8.015999794006348 -10.125,10.241999626159668 -9,10.991999626159668 C-7.875,11.741999626159668 -5,10.031000137329102 -5,10.031000137329102 C-5,10.031000137329102 7.968999862670898,1.875 7.968999862670898,1.875 C7.968999862670898,1.875 9,1.062000036239624 9,0 C9,-1.062000036239624 7.968999862670898,-1.937999963760376 7.968999862670898,-1.937999963760376 C7.968999862670898,-1.937999963760376 -7.031000137329102,-10.875 -7.031000137329102,-10.875z"></path><path stroke-linecap="butt" stroke-linejoin="miter" fill-opacity="0" stroke-miterlimit="4" stroke="rgb(255,255,255)" stroke-opacity="1" stroke-width="0" d=" M-7.031000137329102,-10.875 C-7.031000137329102,-10.875 -8.32800006866455,-11.25 -9.42199993133545,-10.468999862670898 C-10.109999656677246,-9.906999588012695 -10,-7.992000102996826 -10,-7.992000102996826 C-10,-7.992000102996826 -10,8.015999794006348 -10,8.015999794006348 C-10,8.015999794006348 -10.125,10.241999626159668 -9,10.991999626159668 C-7.875,11.741999626159668 -5,10.031000137329102 -5,10.031000137329102 C-5,10.031000137329102 7.968999862670898,1.875 7.968999862670898,1.875 C7.968999862670898,1.875 9,1.062000036239624 9,0 C9,-1.062000036239624 7.968999862670898,-1.937999963760376 7.968999862670898,-1.937999963760376 C7.968999862670898,-1.937999963760376 -7.031000137329102,-10.875 -7.031000137329102,-10.875z"></path></g></g></g></svg>`
    var volume = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88" width="88" height="88" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: translate3d(0px, 0px, 0px);"><defs><clipPath id="__lottie_element_195"><rect width="88" height="88" x="0" y="0"></rect></clipPath><clipPath id="__lottie_element_197"><path d="M0,0 L88,0 L88,88 L0,88z"></path></clipPath></defs><g clip-path="url(#__lottie_element_195)"><g clip-path="url(#__lottie_element_197)" transform="matrix(1,0,0,1,0,0)" opacity="1" style="display: block;"><g transform="matrix(1,0,0,1,28,44)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M15.5600004196167,-25.089000701904297 C15.850000381469727,-24.729000091552734 16,-24.288999557495117 16,-23.839000701904297 C16,-23.839000701904297 16,23.840999603271484 16,23.840999603271484 C16,24.94099998474121 15.100000381469727,25.840999603271484 14,25.840999603271484 C13.550000190734863,25.840999603271484 13.109999656677246,25.680999755859375 12.75,25.400999069213867 C12.75,25.400999069213867 -4,12.00100040435791 -4,12.00100040435791 C-4,12.00100040435791 -8,12.00100040435791 -8,12.00100040435791 C-12.420000076293945,12.00100040435791 -16,8.420999526977539 -16,4.000999927520752 C-16,4.000999927520752 -16,-3.999000072479248 -16,-3.999000072479248 C-16,-8.418999671936035 -12.420000076293945,-11.99899959564209 -8,-11.99899959564209 C-8,-11.99899959564209 -4,-11.99899959564209 -4,-11.99899959564209 C-4,-11.99899959564209 12.75,-25.39900016784668 12.75,-25.39900016784668 C13.609999656677246,-26.089000701904297 14.869999885559082,-25.948999404907227 15.5600004196167,-25.089000701904297z"></path></g></g><g style="display: none;" transform="matrix(1.005582571029663,0,0,1.005582571029663,56.00732421875,44.0004997253418)" opacity="0.03466278523664528"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-4,-13.859000205993652 C0.7799999713897705,-11.08899974822998 4,-5.919000148773193 4,0.0010000000474974513 C4,5.921000003814697 0.7799999713897705,11.090999603271484 -4,13.861000061035156 C-4,13.861000061035156 -4,-13.859000205993652 -4,-13.859000205993652z"></path></g></g><g style="display: none;" transform="matrix(1.012650966644287,0,0,1.012650966644287,64.37825012207031,44.0057487487793)" opacity="0.10899581134639974"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-6.236000061035156,-28.895999908447266 C4.803999900817871,-23.615999221801758 11.984000205993652,-12.456000328063965 11.984000205993652,-0.006000000052154064 C11.984000205993652,12.454000473022461 4.794000148773193,23.624000549316406 -6.265999794006348,28.893999099731445 C-8.255999565124512,29.8439998626709 -10.645999908447266,29.003999710083008 -11.595999717712402,27.003999710083008 C-12.545999526977539,25.013999938964844 -11.696000099182129,22.624000549316406 -9.706000328063965,21.673999786376953 C-1.406000018119812,17.724000930786133 3.9839999675750732,9.343999862670898 3.9839999675750732,-0.006000000052154064 C3.9839999675750732,-9.345999717712402 -1.3960000276565552,-17.715999603271484 -9.675999641418457,-21.676000595092773 C-11.675999641418457,-22.625999450683594 -12.515999794006348,-25.016000747680664 -11.565999984741211,-27.006000518798828 C-10.616000175476074,-29.006000518798828 -8.22599983215332,-29.84600067138672 -6.236000061035156,-28.895999908447266z"></path></g></g><g style="display: none;" transform="matrix(1.0002110004425049,0,0,1.0002110004425049,56.00299835205078,44)" opacity="1"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-4,-13.859000205993652 C0.7799999713897705,-11.08899974822998 4,-5.919000148773193 4,0.0010000000474974513 C4,5.921000003814697 0.7799999713897705,11.090999603271484 -4,13.861000061035156 C-4,13.861000061035156 -4,-13.859000205993652 -4,-13.859000205993652z"></path></g></g><g style="display: none;" transform="matrix(1.000206470489502,0,0,1.000206470489502,64.00399780273438,44.00699996948242)" opacity="1"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-6.236000061035156,-28.895999908447266 C4.803999900817871,-23.615999221801758 11.984000205993652,-12.456000328063965 11.984000205993652,-0.006000000052154064 C11.984000205993652,12.454000473022461 4.794000148773193,23.624000549316406 -6.265999794006348,28.893999099731445 C-8.255999565124512,29.8439998626709 -10.645999908447266,29.003999710083008 -11.595999717712402,27.003999710083008 C-12.545999526977539,25.013999938964844 -11.696000099182129,22.624000549316406 -9.706000328063965,21.673999786376953 C-1.406000018119812,17.724000930786133 3.9839999675750732,9.343999862670898 3.9839999675750732,-0.006000000052154064 C3.9839999675750732,-9.345999717712402 -1.3960000276565552,-17.715999603271484 -9.675999641418457,-21.676000595092773 C-11.675999641418457,-22.625999450683594 -12.515999794006348,-25.016000747680664 -11.565999984741211,-27.006000518798828 C-10.616000175476074,-29.006000518798828 -8.22599983215332,-29.84600067138672 -6.236000061035156,-28.895999908447266z"></path></g></g><g transform="matrix(1,0,0,1,56,44)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-4,-13.859000205993652 C0.7799999713897705,-11.08899974822998 4,-5.919000148773193 4,0.0010000000474974513 C4,5.921000003814697 0.7799999713897705,11.090999603271484 -4,13.861000061035156 C-4,13.861000061035156 -4,-13.859000205993652 -4,-13.859000205993652z"></path></g></g><g transform="matrix(1,0,0,1,64.01399993896484,44.00699996948242)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-6.236000061035156,-28.895999908447266 C4.803999900817871,-23.615999221801758 11.984000205993652,-12.456000328063965 11.984000205993652,-0.006000000052154064 C11.984000205993652,12.454000473022461 4.794000148773193,23.624000549316406 -6.265999794006348,28.893999099731445 C-8.255999565124512,29.8439998626709 -10.645999908447266,29.003999710083008 -11.595999717712402,27.003999710083008 C-12.545999526977539,25.013999938964844 -11.696000099182129,22.624000549316406 -9.706000328063965,21.673999786376953 C-1.406000018119812,17.724000930786133 3.9839999675750732,9.343999862670898 3.9839999675750732,-0.006000000052154064 C3.9839999675750732,-9.345999717712402 -1.3960000276565552,-17.715999603271484 -9.675999641418457,-21.676000595092773 C-11.675999641418457,-22.625999450683594 -12.515999794006348,-25.016000747680664 -11.565999984741211,-27.006000518798828 C-10.616000175476074,-29.006000518798828 -8.22599983215332,-29.84600067138672 -6.236000061035156,-28.895999908447266z"></path></g></g></g></g></svg>`
    var cog = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88" width="88" height="88" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: translate3d(0px, 0px, 0px);"><defs><clipPath id="__lottie_element_263"><rect width="88" height="88" x="0" y="0"></rect></clipPath></defs><g clip-path="url(#__lottie_element_263)"><g transform="matrix(1,0,0,1,44,43.875)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M0,8.125 C-4.420000076293945,8.125 -8,4.545000076293945 -8,0.125 C-8,-4.295000076293945 -4.420000076293945,-7.875 0,-7.875 C4.420000076293945,-7.875 8,-4.295000076293945 8,0.125 C8,4.545000076293945 4.420000076293945,8.125 0,8.125z M0,16.125 C8.84000015258789,16.125 16,8.96500015258789 16,0.125 C16,-8.71500015258789 8.84000015258789,-15.875 0,-15.875 C-8.84000015258789,-15.875 -16,-8.71500015258789 -16,0.125 C-16,8.96500015258789 -8.84000015258789,16.125 0,16.125z M4.539999961853027,27.51099967956543 C3.059999942779541,27.750999450683594 1.5499999523162842,27.871000289916992 0,27.871000289916992 C-1.5499999523162842,27.871000289916992 -3.059999942779541,27.750999450683594 -4.539999961853027,27.51099967956543 C-4.539999961853027,27.51099967956543 -8.699999809265137,32.56100082397461 -8.699999809265137,32.56100082397461 C-9.9399995803833,34.07099914550781 -12.100000381469727,34.46099853515625 -13.789999961853027,33.48099899291992 C-13.789999961853027,33.48099899291992 -21.780000686645508,28.871000289916992 -21.780000686645508,28.871000289916992 C-23.469999313354492,27.891000747680664 -24.209999084472656,25.83099937438965 -23.520000457763672,24.000999450683594 C-23.520000457763672,24.000999450683594 -21.290000915527344,18.06100082397461 -21.290000915527344,18.06100082397461 C-23.3799991607666,15.621000289916992 -25.049999237060547,12.810999870300293 -26.209999084472656,9.76099967956543 C-26.209999084472656,9.76099967956543 -32.65999984741211,8.680999755859375 -32.65999984741211,8.680999755859375 C-34.59000015258789,8.361000061035156 -36,6.690999984741211 -36,4.741000175476074 C-36,4.741000175476074 -36,-4.488999843597412 -36,-4.488999843597412 C-36,-6.439000129699707 -34.59000015258789,-8.109000205993652 -32.65999984741211,-8.428999900817871 C-32.65999984741211,-8.428999900817871 -26.399999618530273,-9.479000091552734 -26.399999618530273,-9.479000091552734 C-25.309999465942383,-12.559000015258789 -23.690000534057617,-15.388999938964844 -21.65999984741211,-17.868999481201172 C-21.65999984741211,-17.868999481201172 -23.959999084472656,-23.999000549316406 -23.959999084472656,-23.999000549316406 C-24.639999389648438,-25.839000701904297 -23.899999618530273,-27.888999938964844 -22.209999084472656,-28.868999481201172 C-22.209999084472656,-28.868999481201172 -14.220000267028809,-33.479000091552734 -14.220000267028809,-33.479000091552734 C-12.529999732971191,-34.45899963378906 -10.380000114440918,-34.069000244140625 -9.130000114440918,-32.558998107910156 C-9.130000114440918,-32.558998107910156 -5.099999904632568,-27.659000396728516 -5.099999904632568,-27.659000396728516 C-3.450000047683716,-27.9689998626709 -1.7400000095367432,-28.128999710083008 0,-28.128999710083008 C1.7400000095367432,-28.128999710083008 3.450000047683716,-27.9689998626709 5.099999904632568,-27.659000396728516 C5.099999904632568,-27.659000396728516 9.130000114440918,-32.558998107910156 9.130000114440918,-32.558998107910156 C10.380000114440918,-34.069000244140625 12.529999732971191,-34.45899963378906 14.220000267028809,-33.479000091552734 C14.220000267028809,-33.479000091552734 22.209999084472656,-28.868999481201172 22.209999084472656,-28.868999481201172 C23.899999618530273,-27.888999938964844 24.639999389648438,-25.839000701904297 23.959999084472656,-23.999000549316406 C23.959999084472656,-23.999000549316406 21.65999984741211,-17.868999481201172 21.65999984741211,-17.868999481201172 C23.690000534057617,-15.388999938964844 25.309999465942383,-12.559000015258789 26.399999618530273,-9.479000091552734 C26.399999618530273,-9.479000091552734 32.65999984741211,-8.428999900817871 32.65999984741211,-8.428999900817871 C34.59000015258789,-8.109000205993652 36,-6.439000129699707 36,-4.488999843597412 C36,-4.488999843597412 36,4.741000175476074 36,4.741000175476074 C36,6.690999984741211 34.59000015258789,8.361000061035156 32.65999984741211,8.680999755859375 C32.65999984741211,8.680999755859375 26.209999084472656,9.76099967956543 26.209999084472656,9.76099967956543 C25.049999237060547,12.810999870300293 23.3799991607666,15.621000289916992 21.290000915527344,18.06100082397461 C21.290000915527344,18.06100082397461 23.520000457763672,24.000999450683594 23.520000457763672,24.000999450683594 C24.209999084472656,25.83099937438965 23.469999313354492,27.891000747680664 21.780000686645508,28.871000289916992 C21.780000686645508,28.871000289916992 13.789999961853027,33.48099899291992 13.789999961853027,33.48099899291992 C12.100000381469727,34.46099853515625 9.9399995803833,34.07099914550781 8.699999809265137,32.56100082397461 C8.699999809265137,32.56100082397461 4.539999961853027,27.51099967956543 4.539999961853027,27.51099967956543z"></path></g></g></g></svg>`
    var webFull = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88" width="88" height="88" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: translate3d(0px, 0px, 0px);"><defs><clipPath id="__lottie_element_174"><rect width="88" height="88" x="0" y="0"></rect></clipPath></defs><g clip-path="url(#__lottie_element_174)"><g transform="matrix(1,0,0,1,44,44)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-14,-20 C-14,-20 -26,-20 -26,-20 C-27.049999237060547,-20 -27.920000076293945,-19.18000030517578 -27.989999771118164,-18.149999618530273 C-27.989999771118164,-18.149999618530273 -28,-18 -28,-18 C-28,-18 -28,-6 -28,-6 C-28,-4.949999809265137 -27.18000030517578,-4.079999923706055 -26.149999618530273,-4.010000228881836 C-26.149999618530273,-4.010000228881836 -26,-4 -26,-4 C-26,-4 -22,-4 -22,-4 C-20.950000762939453,-4 -20.079999923706055,-4.820000171661377 -20.010000228881836,-5.849999904632568 C-20.010000228881836,-5.849999904632568 -20,-6 -20,-6 C-20,-6 -20,-12 -20,-12 C-20,-12 -14,-12 -14,-12 C-12.949999809265137,-12 -12.079999923706055,-12.819999694824219 -12.010000228881836,-13.850000381469727 C-12.010000228881836,-13.850000381469727 -12,-14 -12,-14 C-12,-14 -12,-18 -12,-18 C-12,-19.049999237060547 -12.819999694824219,-19.920000076293945 -13.850000381469727,-19.989999771118164 C-13.850000381469727,-19.989999771118164 -14,-20 -14,-20z M26,-20 C26,-20 14,-20 14,-20 C12.949999809265137,-20 12.079999923706055,-19.18000030517578 12.010000228881836,-18.149999618530273 C12.010000228881836,-18.149999618530273 12,-18 12,-18 C12,-18 12,-14 12,-14 C12,-12.949999809265137 12.819999694824219,-12.079999923706055 13.850000381469727,-12.010000228881836 C13.850000381469727,-12.010000228881836 14,-12 14,-12 C14,-12 20,-12 20,-12 C20,-12 20,-6 20,-6 C20,-4.949999809265137 20.81999969482422,-4.079999923706055 21.850000381469727,-4.010000228881836 C21.850000381469727,-4.010000228881836 22,-4 22,-4 C22,-4 26,-4 26,-4 C27.049999237060547,-4 27.920000076293945,-4.820000171661377 27.989999771118164,-5.849999904632568 C27.989999771118164,-5.849999904632568 28,-6 28,-6 C28,-6 28,-18 28,-18 C28,-19.049999237060547 27.18000030517578,-19.920000076293945 26.149999618530273,-19.989999771118164 C26.149999618530273,-19.989999771118164 26,-20 26,-20z M-22,4 C-22,4 -26,4 -26,4 C-27.049999237060547,4 -27.920000076293945,4.820000171661377 -27.989999771118164,5.849999904632568 C-27.989999771118164,5.849999904632568 -28,6 -28,6 C-28,6 -28,18 -28,18 C-28,19.049999237060547 -27.18000030517578,19.920000076293945 -26.149999618530273,19.989999771118164 C-26.149999618530273,19.989999771118164 -26,20 -26,20 C-26,20 -14,20 -14,20 C-12.949999809265137,20 -12.079999923706055,19.18000030517578 -12.010000228881836,18.149999618530273 C-12.010000228881836,18.149999618530273 -12,18 -12,18 C-12,18 -12,14 -12,14 C-12,12.949999809265137 -12.819999694824219,12.079999923706055 -13.850000381469727,12.010000228881836 C-13.850000381469727,12.010000228881836 -14,12 -14,12 C-14,12 -20,12 -20,12 C-20,12 -20,6 -20,6 C-20,4.949999809265137 -20.81999969482422,4.079999923706055 -21.850000381469727,4.010000228881836 C-21.850000381469727,4.010000228881836 -22,4 -22,4z M26,4 C26,4 22,4 22,4 C20.950000762939453,4 20.079999923706055,4.820000171661377 20.010000228881836,5.849999904632568 C20.010000228881836,5.849999904632568 20,6 20,6 C20,6 20,12 20,12 C20,12 14,12 14,12 C12.949999809265137,12 12.079999923706055,12.819999694824219 12.010000228881836,13.850000381469727 C12.010000228881836,13.850000381469727 12,14 12,14 C12,14 12,18 12,18 C12,19.049999237060547 12.819999694824219,19.920000076293945 13.850000381469727,19.989999771118164 C13.850000381469727,19.989999771118164 14,20 14,20 C14,20 26,20 26,20 C27.049999237060547,20 27.920000076293945,19.18000030517578 27.989999771118164,18.149999618530273 C27.989999771118164,18.149999618530273 28,18 28,18 C28,18 28,6 28,6 C28,4.949999809265137 27.18000030517578,4.079999923706055 26.149999618530273,4.010000228881836 C26.149999618530273,4.010000228881836 26,4 26,4z M28,-28 C32.41999816894531,-28 36,-24.420000076293945 36,-20 C36,-20 36,20 36,20 C36,24.420000076293945 32.41999816894531,28 28,28 C28,28 -28,28 -28,28 C-32.41999816894531,28 -36,24.420000076293945 -36,20 C-36,20 -36,-20 -36,-20 C-36,-24.420000076293945 -32.41999816894531,-28 -28,-28 C-28,-28 28,-28 28,-28z"></path></g></g></g></svg>`
    var full = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88" width="88" height="88" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%; transform: translate3d(0px, 0px, 0px);"><defs><clipPath id="__lottie_element_291"><rect width="88" height="88" x="0" y="0"></rect></clipPath></defs><g clip-path="url(#__lottie_element_291)"><g transform="matrix(1,0,0,1,44,74.22000122070312)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M19.219999313354492,0.2199999988079071 C7.480000019073486,7.630000114440918 -7.480000019073486,7.630000114440918 -19.219999313354492,0.2199999988079071 C-19.219999313354492,0.2199999988079071 -16.219999313354492,-5.78000020980835 -16.219999313354492,-5.78000020980835 C-6.389999866485596,0.75 6.409999847412109,0.75 16.239999771118164,-5.78000020980835 C16.239999771118164,-5.78000020980835 19.219999313354492,0.2199999988079071 19.219999313354492,0.2199999988079071z"></path></g></g><g transform="matrix(1,0,0,1,68.58000183105469,27.895000457763672)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M11.420000076293945,16.104999542236328 C11.420000076293945,16.104999542236328 4.78000020980835,16.104999542236328 4.78000020980835,16.104999542236328 C4.78000020980835,16.104999542236328 4.78000020980835,14.635000228881836 4.78000020980835,14.635000228881836 C4.25,4.054999828338623 -1.940000057220459,-5.425000190734863 -11.420000076293945,-10.164999961853027 C-11.420000076293945,-10.164999961853027 -8.479999542236328,-16.104999542236328 -8.479999542236328,-16.104999542236328 C3.7200000286102295,-10.005000114440918 11.420000076293945,2.4649999141693115 11.420000076293945,16.104999542236328 C11.420000076293945,16.104999542236328 11.420000076293945,16.104999542236328 11.420000076293945,16.104999542236328z"></path></g></g><g transform="matrix(1,0,0,1,19.450000762939453,27.895000457763672)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-4.809999942779541,16.104999542236328 C-4.809999942779541,16.104999542236328 -11.449999809265137,16.104999542236328 -11.449999809265137,16.104999542236328 C-11.449999809265137,2.4649999141693115 -3.75,-10.005000114440918 8.449999809265137,-16.104999542236328 C8.449999809265137,-16.104999542236328 11.449999809265137,-10.164999961853027 11.449999809265137,-10.164999961853027 C1.4900000095367432,-5.204999923706055 -4.809999942779541,4.974999904632568 -4.809999942779541,16.104999542236328 C-4.809999942779541,16.104999542236328 -4.809999942779541,16.104999542236328 -4.809999942779541,16.104999542236328z"></path></g></g><g transform="matrix(1,0,0,1,44.0099983215332,65.96499633789062)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-0.009999999776482582,5.34499979019165 C-5.46999979019165,5.355000019073486 -10.800000190734863,3.7149999141693115 -15.319999694824219,0.6549999713897705 C-15.319999694824219,0.6549999713897705 -12.319999694824219,-5.34499979019165 -12.319999694824219,-5.34499979019165 C-5,0.08500000089406967 5,0.08500000089406967 12.319999694824219,-5.34499979019165 C12.319999694824219,-5.34499979019165 15.319999694824219,0.6549999713897705 15.319999694824219,0.6549999713897705 C10.800000190734863,3.7249999046325684 5.460000038146973,5.355000019073486 -0.009999999776482582,5.34499979019165z"></path></g></g><g transform="matrix(1,0,0,1,62.275001525878906,31.780000686645508)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M9.015000343322754,10.850000381469727 C9.015000343322754,10.850000381469727 9.015000343322754,12.220000267028809 9.015000343322754,12.220000267028809 C9.015000343322754,12.220000267028809 2.434999942779541,12.220000267028809 2.434999942779541,12.220000267028809 C2.434999942779541,12.220000267028809 2.434999942779541,11.220000267028809 2.434999942779541,11.220000267028809 C2.075000047683716,3.740000009536743 -2.305000066757202,-2.9700000286102295 -9.015000343322754,-6.309999942779541 C-9.015000343322754,-6.309999942779541 -6.014999866485596,-12.220000267028809 -6.014999866485596,-12.220000267028809 C-6.014999866485596,-12.220000267028809 -6.014999866485596,-12.220000267028809 -6.014999866485596,-12.220000267028809 C2.7850000858306885,-7.800000190734863 8.524999618530273,1.0099999904632568 9.015000343322754,10.850000381469727 C9.015000343322754,10.850000381469727 9.015000343322754,10.850000381469727 9.015000343322754,10.850000381469727z"></path></g></g><g transform="matrix(1,0,0,1,25.729999542236328,31.780000686645508)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M-2.440000057220459,12.220000267028809 C-2.440000057220459,12.220000267028809 -9.050000190734863,12.220000267028809 -9.050000190734863,12.220000267028809 C-9.050000190734863,1.8700000047683716 -3.2100000381469727,-7.590000152587891 6.050000190734863,-12.220000267028809 C6.050000190734863,-12.220000267028809 9.050000190734863,-6.309999942779541 9.050000190734863,-6.309999942779541 C2.0199999809265137,-2.809999942779541 -2.430000066757202,4.360000133514404 -2.440000057220459,12.220000267028809 C-2.440000057220459,12.220000267028809 -2.440000057220459,12.220000267028809 -2.440000057220459,12.220000267028809z"></path></g></g><g transform="matrix(1,0,0,1,44,57.654998779296875)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M0,4.974999904632568 C-4.110000133514404,4.994999885559082 -8.119999885559082,3.6449999809265137 -11.380000114440918,1.1349999904632568 C-11.380000114440918,1.1349999904632568 -8.319999694824219,-4.974999904632568 -8.319999694824219,-4.974999904632568 C-3.6700000762939453,-0.5049999952316284 3.6700000762939453,-0.5049999952316284 8.319999694824219,-4.974999904632568 C8.319999694824219,-4.974999904632568 11.380000114440918,1.1349999904632568 11.380000114440918,1.1349999904632568 C8.109999656677246,3.634999990463257 4.110000133514404,4.985000133514404 0,4.974999904632568 C0,4.974999904632568 0,4.974999904632568 0,4.974999904632568z"></path></g></g><g transform="matrix(1,0,0,1,55.9900016784668,35.665000915527344)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M6.619999885559082,7.40500020980835 C6.619999885559082,7.40500020980835 6.619999885559082,8.335000038146973 6.619999885559082,8.335000038146973 C6.619999885559082,8.335000038146973 0.009999999776482582,8.335000038146973 0.009999999776482582,8.335000038146973 C0.009999999776482582,3.7850000858306885 -2.549999952316284,-0.375 -6.619999885559082,-2.4049999713897705 C-6.619999885559082,-2.4049999713897705 -3.619999885559082,-8.335000038146973 -3.619999885559082,-8.335000038146973 C2.380000114440918,-5.324999809265137 6.300000190734863,0.6949999928474426 6.619999885559082,7.40500020980835 C6.619999885559082,7.40500020980835 6.619999885559082,7.40500020980835 6.619999885559082,7.40500020980835z"></path></g></g><g transform="matrix(1,0,0,1,31.9950008392334,35.665000915527344)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M6.635000228881836,-2.4049999713897705 C2.565000057220459,-0.375 0.004999999888241291,3.7850000858306885 0.004999999888241291,8.335000038146973 C0.004999999888241291,8.335000038146973 -6.635000228881836,8.335000038146973 -6.635000228881836,8.335000038146973 C-6.635000228881836,1.274999976158142 -2.6449999809265137,-5.184999942779541 3.674999952316284,-8.335000038146973 C3.674999952316284,-8.335000038146973 6.635000228881836,-2.4049999713897705 6.635000228881836,-2.4049999713897705z"></path></g></g><g transform="matrix(1,0,0,1,44,66.322998046875)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M8.319000244140625,-13.677000045776367 C8.319000244140625,-13.677000045776367 19.2189998626709,8.123000144958496 19.2189998626709,8.123000144958496 C13.659000396728516,11.642999649047852 7.068999767303467,13.67300033569336 -0.0010000000474974513,13.67300033569336 C-7.071000099182129,13.67300033569336 -13.66100025177002,11.642999649047852 -19.22100067138672,8.123000144958496 C-19.22100067138672,8.123000144958496 -8.321000099182129,-13.677000045776367 -8.321000099182129,-13.677000045776367 C-6.160999774932861,-11.597000122070312 -3.2309999465942383,-10.32699966430664 -0.0010000000474974513,-10.32699966430664 C3.2290000915527344,-10.32699966430664 6.169000148773193,-11.597000122070312 8.319000244140625,-13.677000045776367z"></path></g></g><g transform="matrix(1,0,0,1,64.68399810791016,27.89699935913086)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M15.314000129699707,16.10700035095215 C15.314000129699707,16.10700035095215 -8.685999870300293,16.10700035095215 -8.685999870300293,16.10700035095215 C-8.685999870300293,11.406999588012695 -11.38599967956543,7.336999893188477 -15.315999984741211,5.367000102996826 C-15.315999984741211,5.367000102996826 -4.576000213623047,-16.10300064086914 -4.576000213623047,-16.10300064086914 C7.214000225067139,-10.192999839782715 15.314000129699707,2.006999969482422 15.314000129699707,16.10700035095215z"></path></g></g><g transform="matrix(1,0,0,1,23.31599998474121,27.89699935913086)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M4.584000110626221,-16.10300064086914 C4.584000110626221,-16.10300064086914 15.314000129699707,5.367000102996826 15.314000129699707,5.367000102996826 C11.383999824523926,7.336999893188477 8.684000015258789,11.406999588012695 8.684000015258789,16.10700035095215 C8.684000015258789,16.10700035095215 -15.315999984741211,16.10700035095215 -15.315999984741211,16.10700035095215 C-15.315999984741211,2.006999969482422 -7.216000080108643,-10.192999839782715 4.584000110626221,-16.10300064086914z"></path></g></g><g transform="matrix(1,0,0,1,44,44)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0,0)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M0,-4 C2.140000104904175,-4 3.890000104904175,-2.319999933242798 4,-0.20000000298023224 C4,-0.20000000298023224 4,0 4,0 C4,0 4,0.20000000298023224 4,0.20000000298023224 C3.890000104904175,2.319999933242798 2.140000104904175,4 0,4 C-2.2100000381469727,4 -4,2.2100000381469727 -4,0 C-4,-2.2100000381469727 -2.2100000381469727,-4 0,-4z"></path></g></g></g></svg>`
    var dot = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" width="18" height="18" preserveAspectRatio="xMidYMid meet"><defs><clipPath id="__lottie_element_25"><rect width="18" height="18" x="0" y="0"></rect></clipPath></defs><g clip-path="url(#__lottie_element_25)"><g transform="matrix(1,0,0,1,8.937000274658203,8.25)" opacity="0.14" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0.07500000298023224,1.2130000591278076)"><path fill="rgb(251,114,153)" fill-opacity="1" d=" M9,-3.5 C9,-3.5 9,3.5 9,3.5 C9,5.707600116729736 7.207600116729736,7.5 5,7.5 C5,7.5 -5,7.5 -5,7.5 C-7.207600116729736,7.5 -9,5.707600116729736 -9,3.5 C-9,3.5 -9,-3.5 -9,-3.5 C-9,-5.707600116729736 -7.207600116729736,-7.5 -5,-7.5 C-5,-7.5 5,-7.5 5,-7.5 C7.207600116729736,-7.5 9,-5.707600116729736 9,-3.5z"></path></g></g><g transform="matrix(1,0,0,1,9.140999794006348,8.67199993133545)" opacity="0.28" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,-0.1509999930858612,0.7990000247955322)"><path fill="rgb(251,114,153)" fill-opacity="1" d=" M8,-3 C8,-3 8,3 8,3 C8,4.931650161743164 6.431650161743164,6.5 4.5,6.5 C4.5,6.5 -4.5,6.5 -4.5,6.5 C-6.431650161743164,6.5 -8,4.931650161743164 -8,3 C-8,3 -8,-3 -8,-3 C-8,-4.931650161743164 -6.431650161743164,-6.5 -4.5,-6.5 C-4.5,-6.5 4.5,-6.5 4.5,-6.5 C6.431650161743164,-6.5 8,-4.931650161743164 8,-3z"></path></g></g><g transform="matrix(0.9883429408073425,-0.7275781631469727,0.6775955557823181,0.920446515083313,7.3224687576293945,-0.7606706619262695)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(0.9937776327133179,-0.11138220876455307,0.11138220876455307,0.9937776327133179,-2.5239999294281006,1.3849999904632568)"><path fill="rgb(0,0,0)" fill-opacity="1" d=" M0.75,-1.25 C0.75,-1.25 0.75,1.25 0.75,1.25 C0.75,1.663925051689148 0.4139249920845032,2 0,2 C0,2 0,2 0,2 C-0.4139249920845032,2 -0.75,1.663925051689148 -0.75,1.25 C-0.75,1.25 -0.75,-1.25 -0.75,-1.25 C-0.75,-1.663925051689148 -0.4139249920845032,-2 0,-2 C0,-2 0,-2 0,-2 C0.4139249920845032,-2 0.75,-1.663925051689148 0.75,-1.25z"></path></g></g><g transform="matrix(1.1436611413955688,0.7535901665687561,-0.6317168474197388,0.9587040543556213,16.0070743560791,2.902894973754883)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(0.992861807346344,0.1192704513669014,-0.1192704513669014,0.992861807346344,-2.5239999294281006,1.3849999904632568)"><path fill="rgb(0,0,0)" fill-opacity="1" d=" M0.75,-1.25 C0.75,-1.25 0.75,1.25 0.75,1.25 C0.75,1.663925051689148 0.4139249920845032,2 0,2 C0,2 0,2 0,2 C-0.4139249920845032,2 -0.75,1.663925051689148 -0.75,1.25 C-0.75,1.25 -0.75,-1.25 -0.75,-1.25 C-0.75,-1.663925051689148 -0.4139249920845032,-2 0,-2 C0,-2 0,-2 0,-2 C0.4139249920845032,-2 0.75,-1.663925051689148 0.75,-1.25z"></path></g></g><g transform="matrix(1,0,0,1,8.890999794006348,8.406000137329102)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,0.09099999815225601,1.1009999513626099)"><path fill="rgb(255,255,255)" fill-opacity="1" d=" M7,-3 C7,-3 7,3 7,3 C7,4.379749774932861 5.879749774932861,5.5 4.5,5.5 C4.5,5.5 -4.5,5.5 -4.5,5.5 C-5.879749774932861,5.5 -7,4.379749774932861 -7,3 C-7,3 -7,-3 -7,-3 C-7,-4.379749774932861 -5.879749774932861,-5.5 -4.5,-5.5 C-4.5,-5.5 4.5,-5.5 4.5,-5.5 C5.879749774932861,-5.5 7,-4.379749774932861 7,-3z"></path><path stroke-linecap="butt" stroke-linejoin="miter" fill-opacity="0" stroke-miterlimit="4" stroke="rgb(0,0,0)" stroke-opacity="1" stroke-width="1.5" d=" M7,-3 C7,-3 7,3 7,3 C7,4.379749774932861 5.879749774932861,5.5 4.5,5.5 C4.5,5.5 -4.5,5.5 -4.5,5.5 C-5.879749774932861,5.5 -7,4.379749774932861 -7,3 C-7,3 -7,-3 -7,-3 C-7,-4.379749774932861 -5.879749774932861,-5.5 -4.5,-5.5 C-4.5,-5.5 4.5,-5.5 4.5,-5.5 C5.879749774932861,-5.5 7,-4.379749774932861 7,-3z"></path></g></g><g transform="matrix(1,0,0,1,8.89900016784668,8.083999633789062)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,-2.5239999294281006,1.3849999904632568)"><path fill="rgb(0,0,0)" fill-opacity="1" d=" M0.875,-1.125 C0.875,-1.125 0.875,1.125 0.875,1.125 C0.875,1.607912540435791 0.48291251063346863,2 0,2 C0,2 0,2 0,2 C-0.48291251063346863,2 -0.875,1.607912540435791 -0.875,1.125 C-0.875,1.125 -0.875,-1.125 -0.875,-1.125 C-0.875,-1.607912540435791 -0.48291251063346863,-2 0,-2 C0,-2 0,-2 0,-2 C0.48291251063346863,-2 0.875,-1.607912540435791 0.875,-1.125z"></path></g></g><g transform="matrix(1,0,0,1,14.008999824523926,8.083999633789062)" opacity="1" style="display: block;"><g opacity="1" transform="matrix(1,0,0,1,-2.5239999294281006,1.3849999904632568)"><path fill="rgb(0,0,0)" fill-opacity="1" d=" M0.8999999761581421,-1.100000023841858 C0.8999999761581421,-1.100000023841858 0.8999999761581421,1.100000023841858 0.8999999761581421,1.100000023841858 C0.8999999761581421,1.596709966659546 0.4967099726200104,2 0,2 C0,2 0,2 0,2 C-0.4967099726200104,2 -0.8999999761581421,1.596709966659546 -0.8999999761581421,1.100000023841858 C-0.8999999761581421,1.100000023841858 -0.8999999761581421,-1.100000023841858 -0.8999999761581421,-1.100000023841858 C-0.8999999761581421,-1.596709966659546 -0.4967099726200104,-2 0,-2 C0,-2 0,-2 0,-2 C0.4967099726200104,-2 0.8999999761581421,-1.596709966659546 0.8999999761581421,-1.100000023841858z"></path></g></g></g></svg>`
    var playBig = `<svg viewBox="0 0 80 80" width="80" height="80" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><path id="pid-53-svgo-a" d="M0 0h80v80H0z"></path><path d="M52.546 8.014a3.998 3.998 0 014.222 3.077c.104.446.093.808.039 1.138a2.74 2.74 0 01-.312.881c-.073.132-.16.254-.246.376l-.257.366-.521.73c-.7.969-1.415 1.926-2.154 2.866l-.015.02a240.945 240.945 0 015.986.341l1.643.123.822.066.41.034.206.018.103.008.115.012c1.266.116 2.516.45 3.677.975a11.663 11.663 0 013.166 2.114c.931.87 1.719 1.895 2.321 3.022a11.595 11.595 0 011.224 3.613c.03.157.046.316.068.474l.015.119.013.112.022.206.085.822.159 1.646c.1 1.098.19 2.198.27 3.298.315 4.4.463 8.829.36 13.255a166.489 166.489 0 01-.843 13.213c-.012.127-.034.297-.053.454a7.589 7.589 0 01-.072.475l-.04.237-.05.236a11.762 11.762 0 01-.74 2.287 11.755 11.755 0 01-5.118 5.57 11.705 11.705 0 01-3.623 1.263c-.158.024-.316.052-.475.072l-.477.053-.821.071-1.644.134c-1.096.086-2.192.16-3.288.23a260.08 260.08 0 01-6.578.325c-8.772.324-17.546.22-26.313-.302a242.458 242.458 0 01-3.287-.22l-1.643-.129-.822-.069-.41-.035-.206-.018c-.068-.006-.133-.01-.218-.02a11.566 11.566 0 01-3.7-.992 11.732 11.732 0 01-5.497-5.178 11.73 11.73 0 01-1.215-3.627c-.024-.158-.051-.316-.067-.475l-.026-.238-.013-.119-.01-.103-.07-.823-.132-1.648a190.637 190.637 0 01-.22-3.298c-.256-4.399-.358-8.817-.258-13.233.099-4.412.372-8.811.788-13.197a11.65 11.65 0 013.039-6.835 11.585 11.585 0 016.572-3.563c.157-.023.312-.051.47-.07l.47-.05.82-.07 1.643-.13a228.493 228.493 0 016.647-.405l-.041-.05a88.145 88.145 0 01-2.154-2.867l-.52-.73-.258-.366c-.086-.122-.173-.244-.246-.376a2.74 2.74 0 01-.312-.881 2.808 2.808 0 01.04-1.138 3.998 3.998 0 014.22-3.077 2.8 2.8 0 011.093.313c.294.155.538.347.742.568.102.11.19.23.28.35l.27.359.532.72a88.059 88.059 0 012.06 2.936 73.036 73.036 0 011.929 3.03c.187.313.373.628.556.945 2.724-.047 5.447-.056 8.17-.038.748.006 1.496.015 2.244.026.18-.313.364-.624.549-.934a73.281 73.281 0 011.93-3.03 88.737 88.737 0 012.059-2.935l.533-.72.268-.359c.09-.12.179-.24.281-.35a2.8 2.8 0 011.834-.881zM30.13 34.631a4 4 0 00-.418 1.42 91.157 91.157 0 00-.446 9.128c0 2.828.121 5.656.364 8.483l.11 1.212a4 4 0 005.858 3.143c2.82-1.498 5.55-3.033 8.193-4.606a177.41 177.41 0 005.896-3.666l1.434-.942a4 4 0 00.047-6.632 137.703 137.703 0 00-7.377-4.708 146.88 146.88 0 00-6.879-3.849l-1.4-.725a4 4 0 00-5.382 1.742z" id="pid-53-svgo-d"></path><filter x="-15.4%" y="-16.3%" width="130.9%" height="132.5%" filterUnits="objectBoundingBox" id="pid-53-svgo-c"><feOffset dy="2" in="SourceAlpha" result="shadowOffsetOuter1"></feOffset><feGaussianBlur stdDeviation="1" in="shadowOffsetOuter1" result="shadowBlurOuter1"></feGaussianBlur><feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.3 0" in="shadowBlurOuter1" result="shadowMatrixOuter1"></feColorMatrix><feOffset in="SourceAlpha" result="shadowOffsetOuter2"></feOffset><feGaussianBlur stdDeviation="3.5" in="shadowOffsetOuter2" result="shadowBlurOuter2"></feGaussianBlur><feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.2 0" in="shadowBlurOuter2" result="shadowMatrixOuter2"></feColorMatrix><feMerge><feMergeNode in="shadowMatrixOuter1"></feMergeNode><feMergeNode in="shadowMatrixOuter2"></feMergeNode></feMerge></filter></defs><g fill="none" fill-rule="evenodd" opacity=".8"><mask id="pid-53-svgo-b" fill="#fff"><use xlink:href="#pid-53-svgo-a"></use></mask><g mask="url(#pid-53-svgo-b)"><use fill="#000" filter="url(#pid-53-svgo-c)" xlink:href="#pid-53-svgo-d"></use><use fill="#FFF" xlink:href="#pid-53-svgo-d"></use></g></g></svg>`

    const registerIcon = NPlayer.Player.Icon.register
    registerIcon('play', createIcon(play))
    registerIcon('volume', createIcon(volume))
    registerIcon('cog', createIcon(cog))
    registerIcon('webEnterFullscreen', createIcon(webFull))
    registerIcon('enterFullscreen', createIcon(full))

    const Screenshot = {
        html: '截图',
        click(player) {
            const canvas = document.createElement('canvas')
            canvas.width = player.video.videoWidth
            canvas.height = player.video.videoHeight
            canvas.getContext('2d').drawImage(player.video, 0, 0, canvas.width, canvas.height)
            canvas.toBlob((blob) => {
                let dataURL = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = dataURL
                link.download = 'pic.png'
                link.style.display = 'none'
                document.body.appendChild(link)
                link.click()
                document.body.removeChild(link)
                URL.revokeObjectURL(dataURL)
            })
        }
    }

    const danmakuOptions = {
        speed: 0.5,
        maxPerInsert: 3000,
        unlimited: true,
        zIndex: 3000,
        poolSize: 3000,
        area: 1,
        items: <?php echo $initial_danmaku; ?>
    }
    // [
    //     { time: 1, text: '弹幕～' }
    // ]

    const hls = new Hls()
    const player = new NPlayer.Player({
        plugins: [new NPlayerDanmaku(danmakuOptions)],
        themeColor: 'rgba(35,173,229, 1)',
        progressBg: 'rgba(35,173,229, 1)',
        volumeProgressBg: 'rgba(35,173,229, 1)',
        progressDot: createIcon(dot, true)(),
        posterPlayEl: createIcon(playBig)(),
        contextMenus: [Screenshot, 'loop', 'pip', 'version']
    })

    hls.attachMedia(player.video)

    hls.on(Hls.Events.MEDIA_ATTACHED, function () {
        hls.loadSource('<?php echo $initial_url; ?>')
    })
    player.mount('#fed-play-iframe')
    
    const waline = new Waline({
        el: '#waline',
        dark: 'auto',
        emoji: [
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/weibo',
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/bilibili',
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/alus',
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/qq',
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/tieba',
            'https://cdn.jsdelivr.net/gh/walinejs/emojis@1.0.0/tw-emoji'
        ],
        visitor: "true",
        comment: "true",
        path: "<?php echo $waline_page_url; ?>",
        serverURL: 'https://comment.maware.cc/'
    });
    
</script>
</body>
</html>
