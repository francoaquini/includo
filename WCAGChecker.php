<?php
/**
 * WCAGChecker - Controlli di Accessibilità WCAG 2.2
 * European Accessibility Act compliance
 */

class WCAGChecker {
    private $dom;
    private $xpath;
    private $content;
    private $url;
    
    public function __construct($dom, $xpath, $content, $url) {
        $this->dom = $dom;
        $this->xpath = $xpath;
        $this->content = $content;
        $this->url = $url;
    }
    
    public function performAllChecks() {
        $issues = [];
        
        // Controlli Livello A
        $issues = array_merge($issues, $this->checkImages());
        $issues = array_merge($issues, $this->checkHeadingStructure());
        $issues = array_merge($issues, $this->checkListStructure());
        $issues = array_merge($issues, $this->checkTableStructure());
        $issues = array_merge($issues, $this->checkKeyboardAccessibility());
        $issues = array_merge($issues, $this->checkSkipLinks());
        $issues = array_merge($issues, $this->checkPageTitle());
        $issues = array_merge($issues, $this->checkPageLanguage());
        $issues = array_merge($issues, $this->checkFormLabels());
        $issues = array_merge($issues, $this->checkHTMLValidity());
        $issues = array_merge($issues, $this->checkARIABasics());
        
        // Controlli Livello AA
        $issues = array_merge($issues, $this->checkColorContrast());
        $issues = array_merge($issues, $this->checkFocusVisible());
        
        $issues = array_merge($issues, $this->checkWCAG22NewCriteria());
        // Controlli European Accessibility Act
        $issues = array_merge($issues, $this->checkEAACompliance());
        
        return $issues;
    }
    
    private function checkImages() {
        $issues = [];
        
        // Immagini senza alt
        $images = $this->xpath->query('//img[not(@alt) or @alt=""]');
        foreach ($images as $img) {
            if (!$this->isProbablyDecorative($img)) {
                $issues[] = [
                    'type' => 'missing_alt_text',
                    'criterion' => '1.1.1',
                    'level' => 'A',
                    'severity' => 'high',
                    'selector' => $this->getElementSelector($img),
                    'description' => 'Immagine senza testo alternativo appropriato',
                    'recommendation' => 'Aggiungere attributo alt con descrizione del contenuto dell\'immagine',
                    'line_number' => $this->getLineNumber($img)
                ];
            }
        }
        
        // Qualità alt text esistente
        $imagesWithAlt = $this->xpath->query('//img[@alt and @alt!=""]');
        foreach ($imagesWithAlt as $img) {
            $alt = $img->getAttribute('alt');
            $src = $img->getAttribute('src');
            $quality = $this->assessAltTextQuality($alt, $src);
            
            if ($quality['score'] < 0.6) {
                $issues[] = [
                    'type' => 'poor_alt_text_quality',
                    'criterion' => '1.1.1',
                    'level' => 'A',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($img),
                    'description' => 'Qualità del testo alternativo migliorabile: ' . implode(', ', $quality['issues']),
                    'recommendation' => 'Migliorare il testo alternativo per essere più descrittivo e specifico',
                    'line_number' => $this->getLineNumber($img)
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkHeadingStructure() {
        $issues = [];
        $headings = $this->xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $headingLevels = [];
        
        foreach ($headings as $heading) {
            $level = intval(substr($heading->tagName, 1));
            $text = trim($heading->textContent);
            $headingLevels[] = ['level' => $level, 'text' => $text, 'element' => $heading];
        }
        
        // Verifica presenza H1
        $h1Count = 0;
        foreach ($headingLevels as $h) {
            if ($h['level'] === 1) $h1Count++;
        }
        
        if ($h1Count === 0) {
            $issues[] = [
                'type' => 'missing_h1',
                'criterion' => '1.3.1',
                'level' => 'A',
                'severity' => 'high',
                'selector' => 'document',
                'description' => 'Manca il titolo principale (h1) nella pagina',
                'recommendation' => 'Aggiungere un elemento h1 che descriva il contenuto principale',
                'line_number' => null
            ];
        } elseif ($h1Count > 1) {
            $issues[] = [
                'type' => 'multiple_h1',
                'criterion' => '1.3.1',
                'level' => 'A',
                'severity' => 'medium',
                'selector' => 'h1',
                'description' => 'Multipli elementi h1 trovati nella pagina',
                'recommendation' => 'Utilizzare un solo h1 per pagina',
                'line_number' => null
            ];
        }
        
        // Verifica sequenza logica
        for ($i = 1; $i < count($headingLevels); $i++) {
            $current = $headingLevels[$i]['level'];
            $previous = $headingLevels[$i-1]['level'];
            
            if ($current > $previous + 1) {
                $issues[] = [
                    'type' => 'heading_sequence',
                    'criterion' => '1.3.1',
                    'level' => 'A',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($headingLevels[$i]['element']),
                    'description' => "Salto nella sequenza dei heading: da h{$previous} a h{$current}",
                    'recommendation' => 'Utilizzare heading in sequenza logica senza saltare livelli',
                    'line_number' => $this->getLineNumber($headingLevels[$i]['element'])
                ];
            }
        }
        
        // Verifica heading vuoti
        foreach ($headingLevels as $h) {
            if (empty(trim($h['text']))) {
                $issues[] = [
                    'type' => 'empty_heading',
                    'criterion' => '1.3.1',
                    'level' => 'A',
                    'severity' => 'high',
                    'selector' => $this->getElementSelector($h['element']),
                    'description' => 'Heading vuoto o senza contenuto testuale',
                    'recommendation' => 'Fornire testo descrittivo per tutti i heading',
                    'line_number' => $this->getLineNumber($h['element'])
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkListStructure() {
        $issues = [];
        $lists = $this->xpath->query('//ul | //ol | //dl');
        
        foreach ($lists as $list) {
            $tagName = strtolower($list->tagName);
            
            if ($tagName === 'ul' || $tagName === 'ol') {
                $items = $this->xpath->query('./li', $list);
                if ($items->length === 0) {
                    $issues[] = [
                        'type' => 'empty_list',
                        'criterion' => '1.3.1',
                        'level' => 'A',
                        'severity' => 'medium',
                        'selector' => $this->getElementSelector($list),
                        'description' => 'Lista vuota senza elementi li',
                        'recommendation' => 'Rimuovere liste vuote o aggiungere elementi li appropriati',
                        'line_number' => $this->getLineNumber($list)
                    ];
                }
            } elseif ($tagName === 'dl') {
                $terms = $this->xpath->query('./dt', $list);
                $definitions = $this->xpath->query('./dd', $list);
                if ($terms->length === 0 || $definitions->length === 0) {
                    $issues[] = [
                        'type' => 'invalid_definition_list',
                        'criterion' => '1.3.1',
                        'level' => 'A',
                        'severity' => 'medium',
                        'selector' => $this->getElementSelector($list),
                        'description' => 'Lista di definizioni senza dt o dd appropriati',
                        'recommendation' => 'Assicurarsi che le liste dl contengano coppie dt/dd',
                        'line_number' => $this->getLineNumber($list)
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    private function checkTableStructure() {
        $issues = [];
        $tables = $this->xpath->query('//table');
        
        foreach ($tables as $table) {
            // Verifica caption o aria-label
            $caption = $this->xpath->query('./caption', $table);
            if ($caption->length === 0 && !$table->hasAttribute('aria-label') && !$table->hasAttribute('aria-labelledby')) {
                $issues[] = [
                    'type' => 'table_missing_caption',
                    'criterion' => '1.3.1',
                    'level' => 'A',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($table),
                    'description' => 'Tabella senza caption o etichetta accessibile',
                    'recommendation' => 'Aggiungere caption, aria-label o aria-labelledby',
                    'line_number' => $this->getLineNumber($table)
                ];
            }
            
            // Verifica headers
            $headers = $this->xpath->query('.//th', $table);
            $rows = $this->xpath->query('.//tr', $table);
            
            if ($rows->length > 1 && $headers->length === 0) {
                $issues[] = [
                    'type' => 'table_missing_headers',
                    'criterion' => '1.3.1',
                    'level' => 'A',
                    'severity' => 'high',
                    'selector' => $this->getElementSelector($table),
                    'description' => 'Tabella di dati senza intestazioni (th)',
                    'recommendation' => 'Utilizzare elementi th per le intestazioni',
                    'line_number' => $this->getLineNumber($table)
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkKeyboardAccessibility() {
        $issues = [];
        $interactiveElements = $this->xpath->query('//a | //button | //input | //select | //textarea');
        
        foreach ($interactiveElements as $element) {
            if ($element->hasAttribute('onclick') && !$element->hasAttribute('onkeypress') && !$element->hasAttribute('onkeydown')) {
                $tagName = strtolower($element->tagName);
                if (!in_array($tagName, ['a', 'button', 'input', 'select', 'textarea'])) {
                    $issues[] = [
                        'type' => 'keyboard_accessibility',
                        'criterion' => '2.1.1',
                        'level' => 'A',
                        'severity' => 'high',
                        'selector' => $this->getElementSelector($element),
                        'description' => 'Elemento interattivo non accessibile da tastiera',
                        'recommendation' => 'Aggiungere gestori eventi tastiera o usare elementi semantici',
                        'line_number' => $this->getLineNumber($element)
                    ];
                }
            }
            
            // Verifica tabindex negativi inappropriati
            $tabindex = $element->getAttribute('tabindex');
            if ($tabindex && intval($tabindex) < -1) {
                $issues[] = [
                    'type' => 'invalid_tabindex',
                    'criterion' => '2.1.1',
                    'level' => 'A',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($element),
                    'description' => 'Valore tabindex non valido: ' . $tabindex,
                    'recommendation' => 'Utilizzare tabindex="0", "1" o "-1" appropriatamente',
                    'line_number' => $this->getLineNumber($element)
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkSkipLinks() {
        $issues = [];
        $skipLinks = $this->xpath->query('//a[contains(@href, "#") and (contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "salta") or contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "skip"))]');
        
        if ($skipLinks->length === 0) {
            $issues[] = [
                'type' => 'skip_links',
                'criterion' => '2.4.1',
                'level' => 'A',
                'severity' => 'medium',
                'selector' => 'document',
                'description' => 'Mancano i link per saltare al contenuto principale',
                'recommendation' => 'Aggiungere link "Salta al contenuto principale" all\'inizio della pagina',
                'line_number' => null
            ];
        }
        
        return $issues;
    }
    
    private function checkPageTitle() {
        $issues = [];
        $titles = $this->xpath->query('//title');
        
        if ($titles->length === 0 || trim($titles->item(0)->textContent) === '') {
            $issues[] = [
                'type' => 'page_title',
                'criterion' => '2.4.2',
                'level' => 'A',
                'severity' => 'high',
                'selector' => 'title',
                'description' => 'Titolo della pagina mancante o vuoto',
                'recommendation' => 'Aggiungere un titolo descrittivo nella sezione <head>',
                'line_number' => null
            ];
        } else {
            // Verifica qualità del titolo
            $title = trim($titles->item(0)->textContent);
            if (strlen($title) < 10 || strlen($title) > 60) {
                $issues[] = [
                    'type' => 'page_title_quality',
                    'criterion' => '2.4.2',
                    'level' => 'A',
                    'severity' => 'medium',
                    'selector' => 'title',
                    'description' => 'Titolo della pagina troppo breve o troppo lungo',
                    'recommendation' => 'Il titolo dovrebbe essere tra 10-60 caratteri e descrivere chiaramente il contenuto',
                    'line_number' => null
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkPageLanguage() {
        $issues = [];
        $htmlLang = $this->xpath->query('//html[@lang]');
        
        if ($htmlLang->length === 0) {
            $issues[] = [
                'type' => 'page_language',
                'criterion' => '3.1.1',
                'level' => 'A',
                'severity' => 'medium',
                'selector' => 'html',
                'description' => 'Lingua della pagina non specificata',
                'recommendation' => 'Aggiungere attributo lang al tag <html>, esempio: <html lang="it">',
                'line_number' => null
            ];
        }
        
        return $issues;
    }
    
    private function checkFormLabels() {
        $issues = [];
        $formInputs = $this->xpath->query('//input[@type!="hidden"] | //select | //textarea');
        
        foreach ($formInputs as $input) {
            $hasLabel = false;
            
            // Controlla label associata tramite ID
            if ($input->hasAttribute('id')) {
                $labels = $this->xpath->query('//label[@for="' . $input->getAttribute('id') . '"]');
                if ($labels->length > 0) {
                    $hasLabel = true;
                }
            }
            
            // Controlla se è contenuto in un label
            $parentLabel = $this->xpath->query('ancestor::label', $input);
            if ($parentLabel->length > 0) {
                $hasLabel = true;
            }
            
            // Controlla attributi ARIA
            if ($input->hasAttribute('aria-label') || $input->hasAttribute('aria-labelledby')) {
                $hasLabel = true;
            }
            
            if (!$hasLabel) {
                $inputType = $input->getAttribute('type') ?: 'text';
                $severity = in_array($inputType, ['submit', 'button', 'reset']) ? 'medium' : 'high';
                
                $issues[] = [
                    'type' => 'form_labels',
                    'criterion' => '3.3.2',
                    'level' => 'A',
                    'severity' => $severity,
                    'selector' => $this->getElementSelector($input),
                    'description' => 'Campo di input senza etichetta associata',
                    'recommendation' => 'Associare una label al campo o utilizzare aria-label/aria-labelledby',
                    'line_number' => $this->getLineNumber($input)
                ];
            }
            
            // Verifica campi obbligatori
            if ($input->hasAttribute('required') || $input->hasAttribute('aria-required')) {
                $hasRequiredIndicator = $this->hasRequiredIndicator($input);
                if (!$hasRequiredIndicator) {
                    $issues[] = [
                        'type' => 'missing_required_indicator',
                        'criterion' => '3.3.2',
                        'level' => 'A',
                        'severity' => 'medium',
                        'selector' => $this->getElementSelector($input),
                        'description' => 'Campo obbligatorio senza indicazione visuale o testuale',
                        'recommendation' => 'Aggiungere asterisco (*) o testo "richiesto" nell\'etichetta',
                        'line_number' => $this->getLineNumber($input)
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    private function checkHTMLValidity() {
        $issues = [];
        
        // Controllo tag HTML multipli
        if (substr_count($this->content, '<html') > 1) {
            $issues[] = [
                'type' => 'html_validity',
                'criterion' => '4.1.1',
                'level' => 'A',
                'severity' => 'high',
                'selector' => 'document',
                'description' => 'Multipli tag HTML nella pagina',
                'recommendation' => 'Assicurarsi che ci sia un solo tag <html> per pagina',
                'line_number' => null
            ];
        }
        
        // Verifica ID duplicati
        preg_match_all('/id=["\']([^"\']+)["\']/', $this->content, $matches);
        if (!empty($matches[1])) {
            $ids = array_count_values($matches[1]);
            foreach ($ids as $id => $count) {
                if ($count > 1) {
                    $issues[] = [
                        'type' => 'duplicate_ids',
                        'criterion' => '4.1.1',
                        'level' => 'A',
                        'severity' => 'high',
                        'selector' => "#$id",
                        'description' => "ID duplicato: $id (trovato $count volte)",
                        'recommendation' => 'Assicurarsi che ogni ID sia unico nella pagina',
                        'line_number' => null
                    ];
                }
            }
        }
        
        return $issues;
    }
    
    private function checkARIABasics() {
        $issues = [];
        
        // Verifica attributi ARIA validi
        $validAriaAttributes = [
            'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden',
            'aria-expanded', 'aria-pressed', 'aria-checked', 'aria-selected',
            'aria-disabled', 'aria-required', 'aria-invalid', 'aria-live',
            'aria-atomic', 'aria-relevant', 'aria-busy', 'aria-controls'
        ];
        
        $elementsWithAria = $this->xpath->query('//*[starts-with(@*, "aria-")]');
        
        foreach ($elementsWithAria as $element) {
            $attributes = $element->attributes;
            for ($i = 0; $i < $attributes->length; $i++) {
                $attr = $attributes->item($i);
                if (strpos($attr->name, 'aria-') === 0) {
                    if (!in_array($attr->name, $validAriaAttributes)) {
                        $issues[] = [
                            'type' => 'invalid_aria_attribute',
                            'criterion' => '4.1.2',
                            'level' => 'A',
                            'severity' => 'medium',
                            'selector' => $this->getElementSelector($element),
                            'description' => "Attributo ARIA non standard: {$attr->name}",
                            'recommendation' => 'Utilizzare solo attributi ARIA riconosciuti dalle specifiche WAI-ARIA',
                            'line_number' => $this->getLineNumber($element)
                        ];
                    }
                }
            }
        }
        
        // Verifica elementi che necessitano di nome accessibile
        $elementsNeedingNames = $this->xpath->query('//button | //a[@href] | //input[@type="submit"] | //input[@type="button"] | //*[@role="button"]');
        
        foreach ($elementsNeedingNames as $element) {
            if (!$this->hasAccessibleName($element)) {
                $issues[] = [
                    'type' => 'missing_accessible_name',
                    'criterion' => '4.1.2',
                    'level' => 'A',
                    'severity' => 'high',
                    'selector' => $this->getElementSelector($element),
                    'description' => 'Elemento interattivo senza nome accessibile',
                    'recommendation' => 'Aggiungere contenuto testuale, aria-label, o aria-labelledby',
                    'line_number' => $this->getLineNumber($element)
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkColorContrast() {
        $issues = [];
        $elementsWithStyle = $this->xpath->query('//*[@style]');
        
        foreach ($elementsWithStyle as $element) {
            $style = $element->getAttribute('style');
            if (strpos($style, 'color') !== false && strpos($style, 'background') !== false) {
                $issues[] = [
                    'type' => 'color_contrast',
                    'criterion' => '1.4.3',
                    'level' => 'AA',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($element),
                    'description' => 'Verifica manuale necessaria per il contrasto colori',
                    'recommendation' => 'Assicurarsi che il contrasto sia almeno 4.5:1 per testo normale',
                    'line_number' => $this->getLineNumber($element)
                ];
            }
        }
        
        return $issues;
    }
    
    private function checkFocusVisible() {
        $issues = [];
        
        // Cerca elementi con outline: none senza alternative
        if (preg_match('/outline\s*:\s*none/i', $this->content)) {
            $issues[] = [
                'type' => 'focus_visible',
                'criterion' => '2.4.7',
                'level' => 'AA',
                'severity' => 'medium',
                'selector' => 'various elements',
                'description' => 'Elementi con outline:none potrebbero non avere focus visibile',
                'recommendation' => 'Fornire indicatori di focus alternativi quando si rimuove outline',
                'line_number' => null
            ];
        }
        
        return $issues;
    }
    
    private function checkEAACompliance() {
        $issues = [];
        
        // Verifica dichiarazione di accessibilità
        $accessibilityLinks = $this->xpath->query('//a[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "accessibilit") or contains(@href, "accessibility")]');
        
        if ($accessibilityLinks->length === 0) {
            $issues[] = [
                'type' => 'missing_accessibility_statement',
                'criterion' => 'EAA',
                'level' => 'AA',
                'severity' => 'high',
                'selector' => 'document',
                'description' => 'Dichiarazione di accessibilità non trovata (richiesta da European Accessibility Act)',
                'recommendation' => 'Aggiungere link alla dichiarazione di accessibilità nel footer o menu principale',
                'line_number' => null
            ];
        }
        
        // Verifica meccanismo di feedback
        $feedbackElements = $this->xpath->query('//a[contains(@href, "mailto:")] | //form[.//textarea] | //a[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "contatt")]');
        
        if ($feedbackElements->length === 0) {
            $issues[] = [
                'type' => 'missing_feedback_mechanism',
                'criterion' => 'EAA',
                'level' => 'AA',
                'severity' => 'medium',
                'selector' => 'document',
                'description' => 'Meccanismo di feedback per problemi di accessibilità non trovato',
                'recommendation' => 'Fornire email, form di contatto o numero di telefono per segnalazioni accessibilità',
                'line_number' => null
            ];
        }
        
        return $issues;
    }
    
    // Metodi helper
    
    private function isProbablyDecorative($img) {
        $src = $img->getAttribute('src');
        $class = $img->getAttribute('class');
        $alt = $img->getAttribute('alt');
        $parent = $img->parentNode;
        
        // Se ha alt="" esplicito, è probabilmente decorativa
        if ($img->hasAttribute('alt') && $alt === '') {
            return true;
        }
        
        // Euristica basata su classe CSS
        if ($class && preg_match('/(icon|decoration|ornament|spacer|bullet|bg-|background)/i', $class)) {
            return true;
        }
        
        // Euristica basata sul nome del file
        if ($src && preg_match('/(icon|decoration|ornament|spacer|bullet|bg[-_])/i', basename($src))) {
            return true;
        }
        
        // Se è dentro un link, probabilmente è informativa
        if ($parent && strtolower($parent->tagName) === 'a') {
            return false;
        }
        
        return false;
    }
    
    private function assessAltTextQuality($alt, $src) {
        $issues = [];
        $score = 1.0;
        
        $alt = trim($alt);
        
        if (strlen($alt) < 3) {
            $issues[] = 'troppo breve';
            $score -= 0.4;
        }
        
        if (strlen($alt) > 125) {
            $issues[] = 'troppo lungo';
            $score -= 0.2;
        }
        
        $filename = basename($src, '.' . pathinfo($src, PATHINFO_EXTENSION));
        if ($filename && stripos($alt, $filename) !== false) {
            $issues[] = 'contiene nome file';
            $score -= 0.3;
        }
        
        $redundantWords = ['image', 'picture', 'photo', 'graphic', 'immagine', 'foto'];
        foreach ($redundantWords as $word) {
            if (stripos($alt, $word) !== false) {
                $issues[] = 'contiene parole ridondanti';
                $score -= 0.2;
                break;
            }
        }
        
        return ['score' => max(0, $score), 'issues' => $issues];
    }
    
    private function hasRequiredIndicator($input) {
        // Cerca indicatori nel label associato
        if ($input->hasAttribute('id')) {
            $labels = $this->xpath->query('//label[@for="' . $input->getAttribute('id') . '"]');
            if ($labels->length > 0) {
                $labelText = $labels->item(0)->textContent;
                if (strpos($labelText, '*') !== false || stripos($labelText, 'richiesto') !== false) {
                    return true;
                }
            }
        }
        
        // Cerca nel label contenitore
        $parentLabel = $this->xpath->query('ancestor::label', $input);
        if ($parentLabel->length > 0) {
            $labelText = $parentLabel->item(0)->textContent;
            if (strpos($labelText, '*') !== false || stripos($labelText, 'richiesto') !== false) {
                return true;
            }
        }
        
        return $input->hasAttribute('aria-required') && $input->getAttribute('aria-required') === 'true';
    }
    
    private function hasAccessibleName($element) {
        // Contenuto testuale visibile
        if (trim($element->textContent) !== '') return true;
        
        // aria-label
        if ($element->hasAttribute('aria-label') && trim($element->getAttribute('aria-label')) !== '') return true;
        
        // aria-labelledby
        if ($element->hasAttribute('aria-labelledby')) return true;
        
        // alt per input type="image"
        if ($element->tagName === 'input' && $element->getAttribute('type') === 'image' && $element->hasAttribute('alt')) return true;
        
        // value per input type="submit" o "button"
        $type = $element->getAttribute('type');
        if ($element->tagName === 'input' && in_array($type, ['submit', 'button', 'reset']) && $element->hasAttribute('value')) return true;
        
        return false;
    }
    
    private function getElementSelector($element) {
        $selector = strtolower($element->tagName);
        
        if ($element->hasAttribute('id')) {
            return $selector . '#' . $element->getAttribute('id');
        }
        
        if ($element->hasAttribute('class')) {
            $classes = explode(' ', trim($element->getAttribute('class')));
            $validClasses = array_filter($classes, function($class) {
                return !empty(trim($class));
            });
            if (!empty($validClasses)) {
                return $selector . '.' . implode('.', array_slice($validClasses, 0, 2));
            }
        }
        
        return $selector;
    }
    
    private function getLineNumber($element) {
        return $element ? $element->getLineNo() : null;
    }
    /**
     * WCAG 2.2 (NEW) – key additional criteria (A/AA focus)
     * Notes:
     * - Some checks are not fully automatable. In those cases we emit "manual_check" issues.
     * - Criteria covered here:
     *   2.4.11 Focus Not Obscured (Minimum) (AA)
     *   2.4.13 Focus Appearance (AA)
     *   2.5.7 Dragging Movements (AA)
     *   2.5.8 Target Size (Minimum) (AA)
     */
    private function checkWCAG22NewCriteria() {
        $issues = [];

        // 2.5.8 Target Size (Minimum) – heuristic: check inline styles/attributes for obvious small targets.
        // If dimensions cannot be detected, mark as manual check required.
        $targets = $this->xpath->query('//a[@href] | //button | //input[@type="button" or @type="submit" or @type="reset"]');
        $smallFound = 0;
        $unknown = 0;

        foreach ($targets as $el) {
            $w = null; $h = null;

            // width/height attributes (rare for interactive elements, but keep)
            if ($el->hasAttribute('width')) $w = (int)$el->getAttribute('width');
            if ($el->hasAttribute('height')) $h = (int)$el->getAttribute('height');

            // inline style width/height
            if ($el->hasAttribute('style')) {
                $style = $el->getAttribute('style');
                if (preg_match('/width\s*:\s*([0-9]+)\s*px/i', $style, $m)) $w = (int)$m[1];
                if (preg_match('/height\s*:\s*([0-9]+)\s*px/i', $style, $m)) $h = (int)$m[1];
                // min-width / min-height
                if (preg_match('/min-width\s*:\s*([0-9]+)\s*px/i', $style, $m) && $w === null) $w = (int)$m[1];
                if (preg_match('/min-height\s*:\s*([0-9]+)\s*px/i', $style, $m) && $h === null) $h = (int)$m[1];
            }

            if ($w === null || $h === null) {
                $unknown++;
                continue;
            }

            // WCAG 2.2 target size minimum: 24x24 CSS px (with exceptions).
            if ($w < 24 || $h < 24) {
                $smallFound++;
                $issues[] = [
                    'type' => 'target_size_too_small',
                    'criterion' => '2.5.8',
                    'level' => 'AA',
                    'severity' => 'medium',
                    'selector' => $this->getElementSelector($el),
                    'description' => 'Target size is smaller than the WCAG 2.2 minimum (24×24 CSS px) – heuristic based on inline dimensions',
                    'recommendation' => 'Increase the clickable/tappable area (padding, min-height/min-width) to at least 24×24 CSS px, respecting exceptions in WCAG 2.2.',
                    'line_number' => $this->getLineNumber($el)
                ];
            }
        }

        if ($unknown > 0) {
            $issues[] = [
                'type' => 'manual_check_target_size',
                'criterion' => '2.5.8',
                'level' => 'AA',
                'severity' => 'info',
                'selector' => 'GLOBAL',
                'description' => 'Target size cannot be fully evaluated automatically (CSS-dependent). Manual verification required.',
                'recommendation' => 'Manually verify that interactive controls meet WCAG 2.2 SC 2.5.8 Target Size (Minimum): 24×24 CSS px minimum, or compliant exceptions.',
                'line_number' => null
            ];
        }

        // 2.5.7 Dragging Movements – generally manual (requires interaction)
        $issues[] = [
            'type' => 'manual_check_dragging_movements',
            'criterion' => '2.5.7',
            'level' => 'AA',
            'severity' => 'info',
            'selector' => 'GLOBAL',
            'description' => 'Dragging movements cannot be evaluated automatically. Manual verification required.',
            'recommendation' => 'Ensure any functionality that uses dragging has an alternative that does not require dragging (e.g., buttons, keyboard controls).',
            'line_number' => null
        ];

        // 2.4.11 Focus Not Obscured (Minimum) – manual (depends on sticky headers, overlays)
        $issues[] = [
            'type' => 'manual_check_focus_not_obscured',
            'criterion' => '2.4.11',
            'level' => 'AA',
            'severity' => 'info',
            'selector' => 'GLOBAL',
            'description' => 'Focus visibility relative to overlays/sticky UI cannot be reliably evaluated automatically. Manual verification required.',
            'recommendation' => 'Verify that keyboard focus is not hidden by sticky headers, cookie banners, chat widgets, or overlays when tabbing through the page.',
            'line_number' => null
        ];

        // 2.4.13 Focus Appearance – partial: we can check if focus styles exist via CSS is hard; mark as manual.
        $issues[] = [
            'type' => 'manual_check_focus_appearance',
            'criterion' => '2.4.13',
            'level' => 'AA',
            'severity' => 'info',
            'selector' => 'GLOBAL',
            'description' => 'Focus indicator appearance is CSS-dependent. Manual verification required.',
            'recommendation' => 'Verify the focus indicator is clearly visible and meets WCAG 2.2 SC 2.4.13 (size/contrast requirements). Avoid removing outlines without a strong replacement.',
            'line_number' => null
        ];

        return $issues;
    }


}
?>