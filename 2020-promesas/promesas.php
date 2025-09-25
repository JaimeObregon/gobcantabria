<?php

const KEYS = [
	'id',
	'conversation_id',
	'created_at',
	'date',
	'time',
	'timezone',
	'user_id',
	'username',
	'name',
	'place',
	'tweet',
	'mentions',
	'urls',
	'photos',
	'replies_count',
	'retweets_count',
	'likes_count',
	'hashtags',
	'cashtags',
	'link',
	'retweet',
	'quote_url',
	'video',
	'near',
	'geo',
	'source',
	'user_rt_id',
	'user_rt',
	'retweet_id',
	'reply_to',
	'retweet_date',
	'translate',
	'trans_src',
	'trans_dest',
];

/**
 * Detecta promesas en un tuit
 * 
 * @param  [array]  $tweet Tuit estructurado en un *array* con las claves nombradas como las columnas del CSV
 * @return boolean        `false` si no se ha detectado profecía
 */
function isProphecy($tweet) {
	/**
	 * Tuits que mencionan un año futuro. Ejemplo: `en 2025`.
	 */
	$regex = '/(en|antes de) (?P<year>2[012]\d{2})/';
	$year = date('Y', strtotime($tweet['date']));
	if (preg_match_all($regex, $tweet['tweet'], $matches)) {
		foreach ($matches['year'] as $match) {
			if ((int) $match > $year) {
				return $tweet;			
			}
		}
	}

	/**
	 *
	 */
	$regex = '/ (com)promete /';
	if (preg_match_all($regex, $tweet['tweet'])) {
		return $tweet;
	}

	/**
	 * El año que viene, el año 202X…
	 */
	$regex = '/ el año /';
	if (preg_match_all($regex, $tweet['tweet'])) {
		return $tweet;
	}

	return false;
}

/**
 * Evalúa si un tuit contiene una promesa de creación de empleo
 * 
 * @param  [array]  $tweet Tuit estructurado en un *array* con las claves nombradas como las columnas del CSV
 * @return boolean        `false` si no se ha detectado promesa
 */
function isJobPromise($tweet) {
	$regex = [
		// Puestos de trabajo
		' puestos de trabajo',

		// Empleos, pero no irregulares ni ya creados
		' empleos(?! (irregulares|creados))',

		// Pleno empleo
		' pleno empleo',

		// Empleos futuros
		' empleará a (más de)?',
	];
	$regex = sprintf('(%s)', implode('|', $regex));
	if (preg_match_all(sprintf('#%s#', $regex), $tweet['tweet'])) {
		return $tweet;
	}

	return false;
}

/**
 * [isMine description]
 * @param  [type]  $tweet [description]
 * @return boolean        [description]
 */
function isMine($tweet) {
	$regex = [
		' min(a|ería)',
		' zinc',
	];
	$regex = sprintf('(%s)', implode('|', $regex));
	if (preg_match_all(sprintf('#%s#i', $regex), $tweet['tweet'])) {
		return $tweet;
	}

	return false;
}

/**
 * [isTrain description]
 * @param  [type]  $tweet [description]
 * @return boolean        [description]
 */
function isTrain($tweet) {
	$regex = [
		' tren(?!es)',
		' alta velocidad',
		' altas prestaciones',
		'TAV',
	];
	$regex = sprintf('(%s)', implode('|', $regex));
	if (preg_match_all(sprintf('#%s#', $regex), $tweet['tweet'])) {
		return $tweet;
	}

	return false;
}

$files = glob('cantabriaes.csv');
foreach ($files as $file) {
	$handle = fopen($file, 'r');

	// Omite la primera línea, que contiene los encabezados de las columnas
	fgetcsv($handle);

	while ($values = fgetcsv($handle)) {
		$tweet = array_combine(KEYS, $values);

		if (isProphecy($tweet)) {
			var_dump($tweet['tweet']);
		}

		if (isJobPromise($tweet)) {
			// var_dump($tweet['tweet']);
		}

		if (isMine($tweet)) {
			// var_dump($tweet['tweet']);
		}

		if (isTrain($tweet)) {
			// var_dump($tweet['tweet']);
		}

	}
	fclose($handle);
}
