<?php
// Keep the legacy global alias tied to the validated application prefix.
$TABLE_PREFIX = PLOGGER_TABLE_PREFIX;
plogger_db_table('tags');

if (basename($_SERVER['PHP_SELF']) == basename( __FILE__ )) {
	// ignorance is bliss
	exit();
}

/** @param string $str
 * @return list<string> */
function parse_tags($str) {
	// 1. Compress all extra whitespaces
	$str = preg_replace('/\s{2,}/', ' ', $str);
	// 2. Extract any phrases in quotes
	preg_match_all('/(\'|")(.*?)(\1)/', $str, $phrases);
	// 3. Now remove the phrases from the string
	$str = preg_replace('/(\'|")(.*?)(\1)/', '', $str);
	// 4. Get rid of whitespaces at the end that may have been left
	$str = trim($str);
	// 5. Get single words
	$words = preg_split('/\s+/', $str);
	// 6. Merge single words and phrases
	$tags = array_merge($phrases[2], $words);

	return $tags;
}

/** @param string $tag
 * @return string */
function urlify_tag($tag) {
	// 1. Format the incoming tag to use in a URL
	return rawurlencode($tag);
}

/**
 * @param int|string $picture_id
 * @return array<int|string,int|string>
 */
function get_picture_tags($picture_id) {
	global $config;
	global $TABLE_PREFIX;
	$picture_id = intval($picture_id);
	$picture_tags = array();

	// TODO: Evaluate whether this method should return the same format for the tags (so in this method it would return an array of arrays, each containing 'id', 'tag' and 'urlified').

	$query = 'SELECT "t2p"."tag_id", "t"."urlified", "t"."tag" FROM ' . plogger_db_table('tag2picture') . ' as "t2p", ' . plogger_db_table('tags') . ' as "t" WHERE "picture_id" = ' . $picture_id . ' AND "t2p"."tag_id" = "t"."id";';
	$result = run_query($query);
	while($tag_row = $result->fetch()) {
		$picture_tags[$tag_row['urlified']] = $tag_row['tag_id'];
	}
	return $picture_tags;
}

/**
 * @param int|string $picture_id
 * @return void
 */
function delete_picture_tags($picture_id) {
	global $TABLE_PREFIX;
	$picture_id = intval($picture_id);
	$sql = 'DELETE FROM ' . plogger_db_table('tag2picture') . ' WHERE picture_id = ' . $picture_id;
	run_query($sql);
}

/**
 * @param string $tag
 * @return array{id:int|string,tag:string,urlified:string}|null
 */
function get_tag_by_name($tag) {
	global $TABLE_PREFIX;
	$existing_tag = array();

	$query = 'SELECT "id", "tag", "urlified" FROM '.plogger_db_table('tags').' WHERE "tag" = ?';
	$result = run_query($query, true, 'Database error.', array($tag));
	$row = $result->fetch();

	if (!is_array($row)) {
		return NULL;
	}
	return array('id' => $row['id'], 'tag' => $row['tag'], 'urlified' => $row['urlified']);
}

/**
 * @param int|string $tag_id
 * @return array{id:int|string,tag:string,urlified:string}|null
 */
function get_tag_by_id($tag_id) {
	global $TABLE_PREFIX;
	$existing_tag = array();
	$tag_id = intval($tag_id);

	$query = 'SELECT "id", "tag", "urlified" FROM ' . plogger_db_table('tags') . ' WHERE "id"=' . $tag_id;
	$result = run_query($query);
	$row = $result->fetch();

	if (!is_array($row)) {
		return NULL;
	}
	return array('id' => $row['tag_id'], 'tag' => $row['tag'], 'urlified' => $row['urlified']);
}

/**
 * @param int|string|null $limit
 * @return null
 */
function get_popular_tags($limit=NULL) {
	global $TABLE_PREFIX;
	// Return a list of the $limit most popular tags
	$query = 'SELECT "t2p"."tag_id", COUNT("t2p"."tag_id") AS "popularity", "t"."tag", "t"."urlified" FROM ' . plogger_db_table('tag2picture') . ' AS "t2p", ' . plogger_db_table('tags') . ' AS "t" WHERE "t"."id"="t2p"."tag_id" GROUP BY "t2p"."tag_id" ORDER BY "popularity" DESC';
	if( isset($limit) ) {
		$limit = intval($limit);
		$query .= ' LIMIT '.$limit;
	}
	return null;
}

/**
 * @param string $tag
 * @return string|false
 */
function insert_tag($tag) {
	global $TABLE_PREFIX, $PLOGGER_DBH;

	$urlified = urlify_tag($tag);
	$sql = 'INSERT INTO '.plogger_db_table('tags').' ("tag","tagdate","urlified") VALUES (?, CURRENT_TIMESTAMP, ?)';
	run_query($sql, true, 'Database error.', array($tag, $urlified));
	$serialobj = getSerialObj('tags');
	return $PLOGGER_DBH->lastInsertID($serialobj);
}

/**
 * @param int|string $picture_id
 * @param string $tags
 * @return list<string|false>
 */
function add_picture_tags($picture_id, $tags) {
	global $TABLE_PREFIX;
	$tags = parse_tags($tags);
	$picture_id = intval($picture_id);

	/* Process any tags for the picture */
	$existing_tags = $existing_rels = array();
	if (sizeof($tags) > 0) {
		$tagsql = implode(', ', array_fill(0, count($tags), '?'));
		$sql = 'SELECT * FROM '.plogger_db_table('tags').' WHERE "tag" IN ('.$tagsql.')';
		$result = run_query($sql, true, 'Database error.', $tags);
		while($tag_row = $result->fetch()) {
			$existing_tags[$tag_row['tag']] = $tag_row['id'];
		}

		$sql = 'SELECT * FROM '.plogger_db_table('tag2picture').' WHERE "picture_id" = ?';
		$result = run_query($sql, true, 'Database error.', array($picture_id));
		while($tag_row = $result->fetch()) {
			$existing_rels[$tag_row['tag_id']] = $tag_row['picture_id'];
		}
	}

	$added_tag_ids = array();
	foreach($tags as $tag) {
		if (!isset($existing_tags[$tag])) {
			// Must be a new tag, register it
			$existing_tags[$tag] = insert_tag($tag);
			$added_tag_ids[] = $existing_tags[$tag];
		}

		if (!isset($existing_rels[$existing_tags[$tag]])) {
			// No connection between tag and picture? create if
			$sql = 'INSERT INTO '.plogger_db_table('tag2picture').' ("picture_id","tag_id","tagdate") VALUES (?, ?, CURRENT_TIMESTAMP)';
			run_query($sql, true, 'Database error.', array($picture_id, intval($existing_tags[$tag])));
		}
	}

	// Make sure that adding the tags 'onetwo' and 'one two' doesn't produce conflicts!
	return $added_tag_ids;
}

/**
 * @param list<int|string> $tag_ids
 * @return void
 */
function delete_tags($tag_ids) {
	global $TABLE_PREFIX;
	if (!$tag_ids) return;
	$tag_ids = array_map('intval', $tag_ids);
	$tagsql = implode(', ', array_fill(0, count($tag_ids), '?'));
	$sql = 'DELETE FROM '.plogger_db_table('tag2picture').' WHERE tag_id IN ('.$tagsql.')';
	run_query($sql, true, 'Database error.', $tag_ids);
	$sql = 'DELETE FROM '.plogger_db_table('tags').' WHERE id IN ('.$tagsql.')';
	run_query($sql, true, 'Database error.', $tag_ids);
}

/**
 * @param int|string $picture_id
 * @param list<int|string> $tag_ids
 * @return null
 */
function remove_picture_tags($picture_id, $tag_ids) {
	// 1. Remove the specified tags from the specified picture.
	return null;
}

/**
 * @param int|string $tag_id
 * @param string $new_name
 * @param bool $change_urlified
 * @return null
 */
function rename_picture_tag($tag_id, $new_name, $change_urlified=true) {
	// 1. Rename the specified tag and update 'urlified' only if specified.
	return null;
}

/**
 * @return null
 */
function purge_unused_tags() {
	// 1. Remove all tags that are not associated with any pictures.
	return null;
}

/**
 * @param int|string $picture_id
 * @param string $tags
 * @return void
 */
function update_picture_tags($picture_id, $tags) {
	global $config, $TABLE_PREFIX, $PLOGGER_DBH;

	$tags = parse_tags($tags);
	$picture_id = intval($picture_id);

	/* Process any tags for the picture */
	$existing_tags = $existing_rels = array();
	if (sizeof($tags) > 0) {
		$tagsql = implode(', ', array_fill(0, count($tags), '?'));
		$sql = 'SELECT * FROM '.plogger_db_table('tags').' WHERE "tag" IN ('.$tagsql.')';
		$result = run_query($sql, true, 'Database error.', $tags);
		while($tag_row = $result->fetch()) {
			$existing_tags[$tag_row['tag']] = $tag_row['id'];
		}

		$sql = 'SELECT * FROM '.plogger_db_table('tag2picture').' WHERE "picture_id" = ?';
		$result = run_query($sql, true, 'Database error.', array($picture_id));
		while($tag_row = $result->fetch()) {
			$existing_rels[$tag_row['tag_id']] = $tag_row['picture_id'];
		}
	}

	foreach($tags as $tag) {
		if (!isset($existing_tags[$tag])) {
			// Must be a new tag, register it
			$path = preg_replace("/[^\w|\.|'|\-|\[|\]]/", "_", $tag);
			$sql = 'INSERT INTO '.plogger_db_table('tags').' ("tag", "tagdate", "path") VALUES (?, CURRENT_TIMESTAMP, ?)';
			$result = run_query($sql, true, 'Database error.', array($tag, $path));
			$serialobj = getSerialObj('tags');
			$existing_tags[$tag] = $PLOGGER_DBH->lastInsertID($serialobj);
		}

		if (!isset($existing_rels[$existing_tags[$tag]])) {
			// No connection between tag and picture? create if
			$sql = 'INSERT INTO '.plogger_db_table('tag2picture').' ("picture_id", "tag_id", "tagdate") VALUES (?, ?, CURRENT_TIMESTAMP)';
			run_query($sql, true, 'Database error.', array($picture_id, intval($existing_tags[$tag])));
		}
	}

	// Now remove links to any tags that have been deleted
	foreach($existing_rels as $tag_id => $pic_id) {
		if (!in_array($tag_id,$existing_tags)) {
			$sql = 'DELETE FROM ' . plogger_db_table('tag2picture') . ' WHERE "picture_id" = ' . $picture_id . ' AND "tag_id" = ' . $tag_id;
			run_query($sql);
		}
	}

}

?>
