<?php
/**
 * Intérprete (parser) del proyecto de Presupuestos de Cantabria 2021.
 * Por Jaime Gómez-Obregón (https://x.com/JaimeObregon)
 *
 * Véase https://x.com/JaimeObregon/status/1329072143271747584
 *
 */

const REGEX = [
	/**
	 * La portada
	 */
	'homepage' => [
		'PROYECTO DE PRESUPUESTOS GENERALES DE',
		'LA COMUNIDAD AUTÓNOMA DE CANTABRIA',
		'ANEXO DE DESARROLLO ECONÓMICO',
		'POR CENTROS GESTORES',
		'2021',
	],

	/**
	 * El índice del documento
	 */
	'index' => 'ÍNDICE\n',

	/**
	 * Cada uno de los conceptos presupuestados
	 */
	'line' => '^(?P<code>\d+(\.-)?) (?P<concept>.+?)( (?P<euros>[\d\.,]+)+)?$',

	/**
	 * El final de un capítulo, con su totalización
	 */
	'total_chapter' => 'TOTAL CAPÍTULO: (?P<euros>[\d\.,]+)',

	/**
	 * El encabezado de una página
	 */
	'heading' => [
		'CÓDIGO DENOMINACIÓN',
		'SECCIÓN (?<codigo_seccion>.\S+)\s(?<seccion>.+)',
		'SERVICIO (?<codigo_servicio>\S+)\s(?<servicio>\n?.+\n*.+)',
		'PROGRAMA (?<codigo_programa>\S+)\s(?<programa>\n?.+\n*.+)',
		'C',
		'A',
		'P',
		'Í',
		'T',
		'U',
		'L',
		'O',
	],

	/**
	 * La página que antecede a cada sección
	 */
	'section' => [
		'SECCIÓN \d{1,2}',
		'(?<section>.+\n*.+)',
		'2021',
		// Hay una errata en la página 229: pone "GAZTOS" en vez de "GASTOS"
		'ANEXO DE DESARROLLO ECONÓMICO DE GA[ZS]TOS POR GESTORES',
		'PROYECTO DE PRESUPUESTOS GENERALES DE LA COMUNIDAD AUTÓNOMA DE CANTABRIA',
	],

	/**
	 * El resumen de costes al final de cada sección
	 */
	'summary' => [
		'(SECCIÓN\n?:\n?(?<section>.+\n*.+))?',
		'COSTE',
		'DEL',
		'PROGRAMA',
		'POR',
		'CAPÍTULOS',
		'(SERVICIO)?',
		'PROGRAMA',
		'DENOMINACIÓN',
	],

	/**
	 * La página que antecede al cuadro-resumen multipágina que hay al final de la última sección
	 */
	'final_summary' => [
		'CUADRO RESUMEN DEL ESTADO DE GASTOS',
		'POR SECCIONES',
		'2021',
		'ANEXO DE DESARROLLO ECONÓMICO DE GASTOS POR GESTORES',
		'PROYECTO DE PRESUPUESTOS GENERALES DE LA COMUNIDAD AUTÓNOMA DE CANTABRIA',
	],

	/**
	 * Un salto de página
	 */
	'page' => '^\x0C- (?P<page>\d{1,3}) -$',
];

/**
 * [checksum description]
 * @param  [type] $items     [description]
 * @param  [type] $total     [description]
 * @param  [type] &$elements [description]
 * @return [type]            [description]
 */
function checksum($items, $total, &$elements) {
    $subconceptos = $conceptos = $articulos = $capitulo = 0;
    $error = false;
    foreach (array_reverse($items) as $item) {
        $elements[] = $item;
        $error = false;
        if (preg_match('#\d{2}\.-#', $item['code'], $matches)) {
            fwrite(STDERR, "Subconcepto.\n");
            $subconceptos += $item['euros'];
        }
        else if (preg_match('#\d{3}#', $item['code'], $matches)) {
            fwrite(STDERR, "Concepto.\n");
            $conceptos += $item['euros'];
            if ($subconceptos) {
                if ($subconceptos != $item['euros']) {
                    fwrite(STDERR, "$subconceptos ≠ {$item['euros']}\n");
                    $error = true;
                }
                $subconceptos = 0;
            }
        }
        else if (preg_match('#\d{2}#', $item['code'], $matches)) {
            fwrite(STDERR, "Artículo.\n");
            $articulos += $item['euros'];
            $capitulo += $item['euros'];
            if ($conceptos) {
                if ($conceptos != $item['euros']) {
                    fwrite(STDERR, "$conceptos ≠ {$item['euros']}\n");
                    $error = true;
                }
                $subconceptos = $conceptos = 0;
            }
        }
        else if (preg_match('#\d#', $item['code'], $matches)) {
            fwrite(STDERR, "Capítulo.\n");
            if ($articulos) {
                if ($articulos != $total) {
                    fwrite(STDERR, "$articulos ≠ $total\n");
                    $error = true;
                }
                $subconceptos = $conceptos = $articulo = 0;
            }
        }
    }
    if ($error) {
        fwrite(STDERR, "💀❌⚠️  ¡Encontré un concepto mal sumado!\n");
        fwrite(STDERR, print_r($items, true));
        die;
    }

    fwrite(STDERR, sprintf("Total del capítulo según el documento: %s\n", $total));
    $name = $items[0]['concept'];
    $section = $items[0]['section'];
    $format = numfmt_create('es_ES', NumberFormatter::CURRENCY);
    $money = numfmt_format_currency($format, $capitulo, 'EUR');
    fwrite(STDERR, sprintf("Total del capítulo '%s' de '%s': %s\n", $name, $section, $money));

    if ($total != $capitulo) {
        die;
    }

    return $capitulo;
}

/**
 * [getChicha description]
 * @param  [type] $page [description]
 * @return [type]       [description]
 */
function getChicha($page) {
    $lines = preg_split("#\n#", $page);

    // Eliminamos líneas vacías
    foreach ($lines as $i => &$line) {
        if (empty($line)) {
            array_splice($lines, $i, 1);
        }
    }

    // Verificamos que el membrete y la cabecera existen,
    // y los eliminamos también
    if (!preg_match('#^- \d{1,3} -$#', $lines[0]) ||
        !preg_match('#^CÓDIGO DENOMINACIÓN$#', $lines[1]) ||
        !preg_match('#^\(Euros sin céntimos\)$#', $lines[35])
    ) {
        fwrite(STDERR, '😔 Esta página no parece tener la estructura prevista.');
        die;
    }

    // Eliminemos membrete y cabecera. Dejemos solo la chicha.
    array_splice($lines, 0, 36);

    return $lines;
}

if (count($argv) !== 2) {
    fwrite(STDERR, sprintf("Uso: %s [fichero]\n", $argv[0]));
    die;
}
else if (!is_file($argv[1])) {
    fwrite(STDERR, sprintf("El fichero no existe: %s\n", $argv[1]));
    die;
}

$lines = file($argv[1]);
$text = implode("\n", $lines);
$stdout = fopen('php://output', 'w');

$pages = preg_split("#\x0C#", $text);

$elements = [];
$chapter = [];
$stats = [
    'count' => 0,
    'budget' => 0,
];

foreach ($pages as $pageNumber => $page) {
    fwrite(STDERR, sprintf("Explorando página nº %s...\n", $pageNumber + 1));
	$type = false;
	$items = [];

    // 💃 ¿La portada?
	if (!$pageNumber) {
		$regex = sprintf('#%s#', implode('\n*', REGEX['homepage']));
		$ocurrences = preg_match_all($regex, $page, $matches);
		if (!$ocurrences) {
			die("La primera página no parece la portada de los Presupuestos 2021.");
		}
		$type = 'homepage';
	}

    // 📄 ¿Una página en blanco?
	if (empty($page)) {
		$type = 'blank';
	}

    // 🔢 ¿El índice del documento?
	$regex = sprintf('#%s#', REGEX['index']);
	$ocurrences = preg_match_all($regex, $page, $matches);
	if ($ocurrences) {
		$type = 'index';
	}

    // 👉 ¿El inicio de una sección?
	$regex = sprintf('#%s#', implode('\n*', REGEX['section']));
	$ocurrences = preg_match_all($regex, $page, $matches);
	if ($ocurrences) {
		$section =  trim(preg_replace('/\s+/', ' ', $matches['section'][0]));
		$type = 'section';
	}

    // 💰 ¿El resumen de coste por capítulos?
	$regex = sprintf('#%s#', implode('\n*', REGEX['summary']));
	$ocurrences = preg_match_all($regex, $page, $matches);
	if ($ocurrences) {
		$type = 'summary';
	}

    // 📈 ¿El cuadro-resumen de coste por secciones?
	$regex = sprintf('#%s#', implode('\n*', REGEX['final_summary']));
	$ocurrences = preg_match_all($regex, $page, $matches);
	if ($ocurrences) {
		$type = 'final-summary';
	}

	$regex = sprintf('#%s#', implode('\n*', REGEX['heading']));
	$ocurrences = preg_match_all($regex, $page, $matches);
	$matches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
	if ($ocurrences) {
		$seccion = trim(preg_replace('/\s+/', ' ', $matches['seccion'][0]));
		$servicio = trim(preg_replace('/\s+/', ' ', $matches['servicio'][0]));
		$programa = trim(preg_replace('/\s+/', ' ', $matches['programa'][0]));
		$_seccion = trim($matches['codigo_seccion'][0]);
		$_servicio = trim($matches['codigo_servicio'][0]);
		$_programa = trim($matches['codigo_programa'][0]);
		// printf("He encontrado un membrete 👍:\n");
		// printf("— Sección {$seccion} ({$_seccion})\n");
		// printf("— Servicio {$servicio} ({$_servicio})\n");
		// printf("— Programa {$programa} ({$_programa})\n");

		$regex = sprintf('#%s\n#', REGEX['total_chapter']);
		$ocurrences = preg_match_all($regex, $page, $matches);
		if ($ocurrences) {
			// printf("👉 Esta página tiene un capítulo íntegro.\n", $section);
			$euros = trim(preg_replace('/\./', '', $matches['euros'][0]));

			$chicha = getChicha($page);
			$lines = array_merge($chapter, $chicha);
			$chapter = [];

			// Eliminemos la línea con el total del capítulo
			if (preg_match(sprintf('#%s#', REGEX['total_chapter']), end($lines))) {
				array_splice($lines, -1, 1);
			}

			// Concatenemos los conceptos que se extienden en más de una línea
			// Hay conceptos hasta de 4 líneas
			$concepts = [];
			$concatenated = '';

			foreach ($lines as $number => $line) {
				if (preg_match('#^\d .+#', $line) ||
					preg_match('#^\d{2,3}(\.-)? (?P<concept>.+?) (?P<euros>[\d\.,]+)+$#', $line)) {
					// echo "[ok] $line\n";
					$concepts[] .= empty($concatenated) ? $line : trim($concatenated);
					if ($concatenated) {
						$concepts[] .= $line;
					}
					$concatenated = '';
					continue;
				}

				// echo "[concatenable] $line\n";
				$concatenated .= ' ' . $line;
				if (preg_match('#^[\d|\.]+$#', $line)) {
					$concepts[] .= trim($concatenated);
					$concatenated = '';
				}
			}

			foreach ($concepts as $line) {
				$regex = sprintf('#%s#', REGEX['line']);
				$ocurrences = preg_match($regex, $line, $matches);

				if (!empty($matches)) {
					$_euros = isset($matches['euros']) ? trim(preg_replace('/\./', '', $matches['euros'])) : false;
					$items[] = [
						'section' => $seccion,
						'service' => $servicio,
						'program' => $programa,
						'sectionCode' => $_seccion,
						'serviceCode' => $_servicio,
						'programCode' => $_programa,
						'code' => $matches['code'],
						'concept' => $matches['concept'],
						'euros' => empty($_euros) ? false : $_euros,
					];
				}
			}

            $stats['count'] += count($items);
			$stats['budget'] += checksum($items, $euros, $elements);
		}
		else {
			// echo "⚠️ Esta página tiene un trozo de un capítulo solamente.\n";
			$chicha = getChicha($page);
			$chapter = array_merge($chapter, $chicha);
		}

		$type = 'content';
	}

	if (!$type) {
        fwrite(STDERR, 'No reconozco esta página.');
		die;
	}
	if ($pageNumber >= 623) {
		break;
	}
}

$format = numfmt_create('es_ES', NumberFormatter::CURRENCY);
$euros = numfmt_format_currency($format, $stats['budget'], 'EUR');
$count = $stats['count'];
fwrite(STDERR, sprintf("¡Terminé! Hay %s elementos para analizar, que suman %s.\n", $count, $euros));

foreach ($elements as $element) {
	fputcsv($stdout, $element);
}

