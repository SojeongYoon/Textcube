<?php
/// Copyright (c) 2004-2009, Needlworks / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/doc/LICENSE, /doc/COPYRIGHT)

$entriesView = '';

if (isset($cache->contents)) {
	$entriesView = $cache->contents;
	if(strpos($cache->name,'keyword')!==false) $isKeylog = true;
} else if(isset($entries)) {
	$totalTags = array();
	$entryRsses = '';
	foreach ($entries as $entry) {
//		$entryRsses .= '<link rel="alternate" type="application/rss+xml" '.
//			'title="Trackback: '.htmlspecialchars($entry['title']).' - '.htmlspecialchars($blog['title']).'" '.
//			'href="'.$defaultURL.'/rss/trackback/'.$entry['id'].'" />'.CRLF;
//		$entryRsses .= '<link rel="alternate" type="application/rss+xml" '.
//			'title="Comment: '.htmlspecialchars($entry['title']).' - '.htmlspecialchars($blog['title']).'" '.
//			'href="'.$defaultURL.'/rss/comment/'.$entry['id'].'" />'.CRLF;
		$entryRsses .= '<link rel="alternate" type="application/rss+xml" '.
			'title="Responses (RSS) : '.htmlspecialchars($entry['title']).' - '.htmlspecialchars($blog['title']).'" '.
			'href="'.$defaultURL.'/rss/response/'.$entry['id'].'" />'.CRLF.
			'<link rel="alternate" type="application/atom+xml" '.
			'title="Responses (ATOM) : '.htmlspecialchars($entry['title']).' - '.htmlspecialchars($blog['title']).'" '.
			'href="'.$defaultURL.'/atom/response/'.$entry['id'].'" />'.CRLF;
	}
	if( getBlogSetting('useFOAF',1) && rtrim( $suri['url'], '/' ) == $pathURL ) {
		/* same code exists in cover.php */
		$foafDiscovery = "<link rel=\"meta\" type=\"application/rdf+xml\" title=\"FOAF\" href=\"$defaultURL/foaf\" />\n";
	} else {
		$foafDiscovery = "";
	}
	dress('SKIN_head_end', $foafDiscovery.$entryRsses."[##_SKIN_head_end_##]", $view);
	dress('foaf_url', "$defaultURL/foaf", $view);
	
	foreach ($entries as $entry) {
		if ($suri['directive'] == '/notice')
			$permalink = "$blogURL/notice/" . ($blog['useSloganOnPost'] ? URL::encode($entry['slogan'], $service['useEncodedURL']) : $entry['id']);
		else if ($suri['directive'] == '/page')
			$permalink = "$blogURL/page/" . ($blog['useSloganOnPost'] ? URL::encode($entry['slogan'], $service['useEncodedURL']) : $entry['id']);
		else
			$permalink = "$blogURL/" . ($blog['useSloganOnPost'] ? "entry/" . URL::encode($entry['slogan'],$service['useEncodedURL']) : $entry['id']);

		if ($entry['category'] == - 1) { // This is keylog
			$entryView = $skin->keylogItem;
			dress('keylog_rep_date', fireEvent('ViewKeylogDate', Timestamp::format5($entry['published']), $entry['published']), $entryView);
			dress('keylog_rep_title', htmlspecialchars(fireEvent('ViewKeylogTitle', $entry['title'], $entry['id'])), $entryView);
			// 사용자가 작성한 본문은 lib/piece/blog/end.php의 removeAllTags() 다음에 처리하기 위한 조치.
			$contentContainer["keylog_{$entry['id']}"] = getEntryContentView($blogid, $entry['id'], $entry['content'], $entry['contentformatter'], null, 'Keylog');
			dress('keylog_rep_desc', setTempTag("keylog_{$entry['id']}"), $entryView);
			dress('keylog_rep_author', User::getName($entry['userid']), $entryView);
			$entriesView .= $entryView;
			$isKeylog = true;
		} else if ($entry['category'] == - 2) { // This is notice
			$entryView = $skin->noticeItem;
			dress('notice_rep_microformat_published', Timestamp::getISO8601($entry['published']), $entryView);
			dress('notice_rep_microformat_updated', Timestamp::getISO8601($entry['modified']), $entryView);
			dress('notice_rep_date', fireEvent('ViewNoticeDate', Timestamp::format5($entry['published']), $entry['published']), $entryView);
			dress('notice_rep_title', htmlspecialchars(fireEvent('ViewNoticeTitle', $entry['title'], $entry['id'])), $entryView);
			dress('notice_rep_link', $permalink, $entryView);
			
			// 사용자가 작성한 본문은 lib/piece/blog/end.php의 removeAllTags() 다음에 처리하기 위한 조치.
			$contentContainer["notice_{$entry['id']}"] = getEntryContentView($blogid, $entry['id'], $entry['content'], $entry['contentformatter'], getKeywordNames($blogid), 'Notice');
			dress('notice_rep_desc', setTempTag("notice_{$entry['id']}"), $entryView);
			dress('notice_rep_author', User::getName($entry['userid']), $entryView);
			$entriesView .= $entryView;

		} else if (doesHaveOwnership() || ($entry['visibility'] >= 2) || (isset($_COOKIE['GUEST_PASSWORD']) && (trim($_COOKIE['GUEST_PASSWORD']) == trim($entry['password'])))) {	// This is post
			$entryView = $skin->entry;
			$entryView = '<a id="entry_'.$entry['id'].'"></a>'.CRLF.$entryView;

			dress('tb', getTrackbacksView($entry, $skin, $entry['accepttrackback']), $entryView);
			if ($skinSetting['expandComment'] == 1 || (($suri['directive'] == '/' || $suri['directive'] == '/entry') && $suri['value'] != '')) {
				$style = 'block';
			} else {
				$style = 'none';
			}
			dress('rp', "<div id=\"entry{$entry['id']}Comment\" style=\"display:$style\">" . getCommentView($entry, $skin) . "</div>", $entryView);
			$tagLabelView = $skin->tagLabel;
			$entryTags = getTags($entry['blogid'], $entry['id']);
			if (sizeof($entryTags) > 0) {
				$tags = array();
				$relTag = getBlogSetting('useMicroformat', 3)>1 && (count($entries) == 1 || !empty($skin->hentryExisted) );
				foreach ($entryTags as $entryTag) {
					$tags[$entryTag['name']] = "<a href=\"$defaultURL/tag/" . (getBlogSetting('useSloganOnTag',true) ? URL::encode($entryTag['name'],$service['useEncodedURL']) : $entryTag['id']). '"' . ($relTag ? ' rel="tag"' : '') . '>' . htmlspecialchars($entryTag['name']) . '</a>';
					array_push($totalTags,$entryTag['name']);
				}
				$tags = fireEvent('ViewTagLists', $tags, $entry['id']);
				dress('tag_label_rep', implode(",\r\n", array_values($tags)), $tagLabelView);
				dress('tag_label', $tagLabelView, $entryView);
			}
			if (doesHaveOwnership() && ($entry['userid'] == getUserId() || Acl::check('group.editors')===true)) {
				$managementView = $skin->management;
				$useEncodedURL = false;
				if( isset($service['useEncodedURL'])) {
					$useEncodedURL = $service['useEncodedURL'];
				}
				dress('s_ad_m_link', "$blogURL/owner/entry/edit/{$entry['id']}?returnURL=" . ($useEncodedURL ? $permalink : str_replace('%2F', '/', rawurlencode($permalink))), $managementView);
				dress('s_ad_m_onclick', "editEntry({$entry['id']},'".($useEncodedURL ? $permalink : str_replace('%2F', '/', rawurlencode($permalink)))."'); return false;", $managementView);
				dress('s_ad_s1_label', getEntryVisibilityName($entry['visibility']), $managementView);
				if ($entry['visibility'] < 2) {
					dress('s_ad_s2_label', _text('공개로 변경합니다'), $managementView);
					dress('s_ad_s2_onclick', "changeVisibility({$entry['id']}, 2); return false;", $managementView);
				} else {
					dress('s_ad_s2_label', _text('비공개로 변경합니다'), $managementView);
					dress('s_ad_s2_onclick', "changeVisibility({$entry['id']}, 0); return false;", $managementView);
				}
				dress('s_ad_t_onclick', "sendTrackback({$entry['id']}); return false;", $managementView);
				dress('s_ad_d_onclick', "deleteEntry({$entry['id']}); return false;", $managementView);
				dress('ad_div', $managementView, $entryView);
			}
			$author = User::getName($entry['userid']);
			dress('article_rep_author', fireEvent('ViewPostAuthor', $author, $entry['id']), $entryView);
			dress('article_rep_id', $entry['id'], $entryView);
			dress('article_rep_link', $permalink, $entryView);
			dress('article_rep_rp_rssurl', $defaultURL.'/rss/comment/'.$entry['id'], $entryView);
			dress('article_rep_tb_rssurl', $defaultURL.'/rss/trackback/'.$entry['id'], $entryView);
			dress('article_rep_response_rssurl', $defaultURL.'/rss/response/'.$entry['id'], $entryView);
			dress('article_rep_rp_atomurl', $defaultURL.'/atom/comment/'.$entry['id'], $entryView);
			dress('article_rep_tb_atomurl', $defaultURL.'/atom/trackback/'.$entry['id'], $entryView);
			dress('article_rep_response_atomurl', $defaultURL.'/atom/response/'.$entry['id'], $entryView);
			dress('article_rep_category_body_id',getCategoryBodyIdById($blogid,$entry['category']) ? getCategoryBodyIdById($blogid,$entry['category']) : 'tt-body-category',$entryView);
			dress('article_rep_title', htmlspecialchars(fireEvent('ViewPostTitle', $entry['title'], $entry['id'])), $entryView);
			// 사용자가 작성한 본문은 lib/piece/blog/end.php의 removeAllTags() 다음에 처리하기 위한 조치.
			$contentContainer["article_{$entry['id']}"] = getEntryContentView($blogid, $entry['id'], $entry['content'], $entry['contentformatter'], getKeywordNames($blogid));
			dress('article_rep_desc', setTempTag("article_{$entry['id']}"), $entryView);
			dress('article_rep_category', htmlspecialchars(empty($entry['category']) ? _text('분류없음') : $entry['categoryLabel'], $entry['id']), $entryView);
			dress('article_rep_category_link', "$blogURL/category/".(empty($entry['category']) ? "" : 
($blog['useSloganOnCategory'] ? URL::encode($entry['categoryLabel'],$service['useEncodedURL']) : $entry['category'])),$entryView);
			dress('article_rep_microformat_published', Timestamp::getISO8601($entry['published']), $entryView);
			dress('article_rep_microformat_updated', Timestamp::getISO8601($entry['modified']), $entryView);
			dress('article_rep_date', fireEvent('ViewPostDate', Timestamp::format5($entry['published']), $entry['published']), $entryView);
			dress('entry_archive_link', "$blogURL/archive/" . Timestamp::getDate($entry['published']), $entryView);
			if ($entry['acceptcomment'] || ($entry['comments'] > 0))
				dress('article_rep_rp_link', "toggleLayer('entry{$entry['id']}Comment'); return false", $entryView);
			else
				dress('article_rep_rp_link', "return false", $entryView);
		
			dress('article_rep_rp_cnt_id', "commentCount{$entry['id']}", $entryView);
			list($tempTag, $commentView) = getCommentCountPart($entry['comments'], $skin);
			dress($tempTag, $commentView, $entryView);
		
			if ($entry['accepttrackback'] || ($entry['trackbacks'] > 0))
				dress('article_rep_tb_link', "toggleLayer('entry{$entry['id']}Trackback'); return false", $entryView);
			else
				dress('article_rep_tb_link', "return false", $entryView);
		
			dress('article_rep_tb_cnt_id', "trackbackCount{$entry['id']}", $entryView);
			list($tempTag, $trackbackView) = getTrackbackCountPart($entry['trackbacks'], $skin);
			dress($tempTag, $trackbackView, $entryView);
			$entriesView .= $entryView;
		} else {	// Protected entries
			$protectedEntryView = $skin->entryProtected;
			$author = User::getName($entry['userid']);
			dress('article_rep_author', fireEvent('ViewPostAuthor', $author, $entry['id']), $protectedEntryView);
			dress('article_rep_id', $entry['id'], $protectedEntryView);
			dress('article_rep_link', $permalink, $protectedEntryView);
			dress('article_rep_title', htmlspecialchars(fireEvent('ViewPostTitle', $entry['title'], $entry['id'])), $protectedEntryView);
			dress('article_rep_date', fireEvent('ViewPostDate', Timestamp::format5($entry['published'])), $protectedEntryView);
			dress('article_password', "entry{$entry['id']}password", $protectedEntryView);
			dress('article_dissolve', "reloadEntry({$entry['id']});", $protectedEntryView);
			if (isset($_POST['partial']))
				$entriesView .= $protectedEntryView;
			else
				$entriesView .= "<div id=\"entry{$entry['id']}\">$protectedEntryView</div>";
		}
	}
	if(count($entries) > 1 || (count($entries) == 1 && empty($suri['value']))) {
		unset($totalTags);
	}
	if(count($entries) == 1) {	// Adds trackback RDF
		$info = array();
		$info['title']        = htmlspecialchars($entries[0]['title']);
		$info['permalink']    = $permalink;
		$info['trackbackURL'] = $defaultURL."/trackback/".$entries[0]['id'];
		$entriesView .= getTrackbackRDFView($blogid, $info);
	}
	if(isset($cache)) {
		$cache->contents = revertTempTags(removeAllTags($entriesView));
		if(isset($paging)) $cache->dbContents = $paging;
		$cache->update();
	}
}
$view = str_replace( "[##_article_rep_##]", "<div class=\"hfeed\">[##_article_rep_##]</div>", $view);
if(isset($isKeylog) && $isKeylog) {
	dressInsertBefore('list', $entriesView, $view);
	$isKeylog = false;
} else {
	if (isset($cache->contents)) {
		dressInsertBefore('article_rep', $entriesView, $view);
	}else{
		dress('article_rep', $entriesView, $view);
	}
}
?>