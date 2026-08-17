<?php
/**
 * SimplePdf — gerador de PDF em PHP puro, sem dependências.
 *
 * O projeto não usa Composer para bibliotecas de PDF (os exports existentes em
 * CampaignController/DashboardController são stubs que devolvem HTML). Este
 * helper cobre o necessário para relatórios de texto: títulos, parágrafos,
 * tabelas, barras de distribuição e blocos monoespaçados (transcrições).
 *
 * Usa as fontes base do PDF (Helvetica, Helvetica-Bold, Courier) com
 * WinAnsiEncoding — cobre acentuação do português sem embutir arquivo de fonte,
 * e o texto continua extraível (importante quando o PDF vai ser lido por uma IA).
 *
 * Uso:
 *   $pdf = new SimplePdf('Relatório de Conversas');
 *   $pdf->heading('Resumo');
 *   $pdf->paragraph('Texto...');
 *   $pdf->table(['Etapa', 'Total'], [['Proposta', '42']], [70, 30]);
 *   file_put_contents('x.pdf', $pdf->output());
 */

namespace App\Helpers;

class SimplePdf
{
    // A4 em pontos
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 42.0;
    private const BOTTOM_LIMIT = 56.0;

    private const FONT_REGULAR = 'F1';
    private const FONT_BOLD = 'F2';
    private const FONT_MONO = 'F3';

    /** @var string[] Conteúdo de cada página */
    private array $pages = [];

    private string $buffer = '';
    private float $y = 0.0;
    private int $pageNumber = 0;

    private string $title;
    private string $subtitle;

    /** Largura útil da linha */
    private float $contentWidth;

    /** Tabelas de largura dos glifos (unidades/1000) para 32..126 */
    private static array $widthsRegular = [];
    private static array $widthsBold = [];

    public function __construct(string $title, string $subtitle = '')
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->contentWidth = self::PAGE_WIDTH - (self::MARGIN * 2);

        self::initWidths();
        $this->addPage();
    }

    // ------------------------------------------------------------------
    // PÁGINAS
    // ------------------------------------------------------------------

    public function addPage(): void
    {
        $this->flushPage();

        $this->pageNumber++;
        $this->buffer = '';
        $this->y = self::PAGE_HEIGHT - self::MARGIN;

        // Cabeçalho corrido a partir da segunda página
        if ($this->pageNumber > 1) {
            $this->writeLine($this->title, self::FONT_BOLD, 8, [0.45, 0.45, 0.45]);
            $this->y -= 2;
            $this->rule(0.85);
            $this->y -= 6;
        }
    }

    private function flushPage(): void
    {
        if ($this->pageNumber === 0) {
            return;
        }

        // Rodapé: número da página
        $footer = 'Página ' . $this->pageNumber;
        $width = $this->textWidth($footer, self::FONT_REGULAR, 8);
        $x = self::PAGE_WIDTH - self::MARGIN - $width;

        $this->buffer .= sprintf(
            "BT /%s 8 Tf 0.5 0.5 0.5 rg %.2f %.2f Td (%s) Tj ET\n",
            self::FONT_REGULAR,
            $x,
            self::MARGIN - 16,
            $this->escape($footer)
        );

        $this->pages[] = $this->buffer;
    }

    /**
     * Garante espaço vertical; quebra a página se necessário.
     */
    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < self::BOTTOM_LIMIT) {
            $this->addPage();
        }
    }

    // ------------------------------------------------------------------
    // BLOCOS DE CONTEÚDO
    // ------------------------------------------------------------------

    /**
     * Capa do relatório
     */
    public function cover(array $meta = []): void
    {
        $this->y -= 40;

        $this->writeWrapped($this->title, self::FONT_BOLD, 22, [0.1, 0.1, 0.1], 28);

        if ($this->subtitle !== '') {
            $this->y -= 6;
            $this->writeWrapped($this->subtitle, self::FONT_REGULAR, 12, [0.35, 0.35, 0.35], 16);
        }

        $this->y -= 18;
        $this->rule(1.2);
        $this->y -= 14;

        foreach ($meta as $label => $value) {
            $this->keyValue($label, (string)$value);
        }

        $this->y -= 10;
    }

    public function heading(string $text, int $level = 1): void
    {
        $size = $level === 1 ? 15 : ($level === 2 ? 12 : 10);
        $lineHeight = $size + 4;

        $this->ensureSpace($lineHeight + 14);
        $this->y -= $level === 1 ? 16 : 10;

        $this->writeWrapped($text, self::FONT_BOLD, $size, [0.1, 0.1, 0.1], $lineHeight);

        if ($level === 1) {
            $this->y -= 3;
            $this->rule(0.9);
        }

        $this->y -= 6;
    }

    public function paragraph(string $text, float $size = 9.5, array $color = [0.15, 0.15, 0.15]): void
    {
        if (trim($text) === '') {
            return;
        }

        $this->writeWrapped($text, self::FONT_REGULAR, $size, $color, $size + 3.5);
        $this->y -= 5;
    }

    public function keyValue(string $label, string $value): void
    {
        $this->ensureSpace(14);

        // A coluna do valor começa depois do rótulo — rótulo mais largo que a
        // coluna padrão empurra o valor, em vez de ficar por baixo dele
        $labelWidth = max(130.0, $this->textWidth($label, self::FONT_BOLD, 9) + 10);
        $labelWidth = min($labelWidth, $this->contentWidth * 0.6);

        $this->writeAt(self::MARGIN, $this->y, $label, self::FONT_BOLD, 9, [0.35, 0.35, 0.35]);

        // Valor pode quebrar linha; recua na coluna da direita
        $lines = $this->wrap($value, self::FONT_REGULAR, 9, $this->contentWidth - $labelWidth);

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $this->y -= 12;
                $this->ensureSpace(12);
            }
            $this->writeAt(self::MARGIN + $labelWidth, $this->y, $line, self::FONT_REGULAR, 9, [0.1, 0.1, 0.1]);
        }

        $this->y -= 14;
    }

    /**
     * Tabela simples.
     *
     * @param string[] $headers
     * @param array[]  $rows
     * @param float[]  $widths percentuais que somam 100
     */
    public function table(array $headers, array $rows, array $widths = []): void
    {
        if (empty($headers)) {
            return;
        }

        $columns = count($headers);

        if (count($widths) !== $columns) {
            $widths = array_fill(0, $columns, 100 / $columns);
        }

        $columnWidths = array_map(fn($percent) => $this->contentWidth * ($percent / 100), $widths);

        $this->ensureSpace(30);
        $this->tableHeader($headers, $columnWidths);

        foreach ($rows as $row) {
            $row = array_values($row);

            // Quebra cada célula e descobre a altura da linha
            $cellLines = [];
            $maxLines = 1;

            foreach ($row as $index => $cell) {
                $available = ($columnWidths[$index] ?? $columnWidths[0]) - 8;
                $lines = $this->wrap((string)$cell, self::FONT_REGULAR, 8.5, $available);
                $cellLines[$index] = $lines;
                $maxLines = max($maxLines, count($lines));
            }

            $rowHeight = ($maxLines * 11) + 4;

            if ($this->y - $rowHeight < self::BOTTOM_LIMIT) {
                $this->addPage();
                $this->tableHeader($headers, $columnWidths);
            }

            $x = self::MARGIN;

            foreach ($cellLines as $index => $lines) {
                $lineY = $this->y;

                foreach ($lines as $line) {
                    $this->writeAt($x + 2, $lineY, $line, self::FONT_REGULAR, 8.5, [0.15, 0.15, 0.15]);
                    $lineY -= 11;
                }

                $x += $columnWidths[$index] ?? 0;
            }

            $this->y -= $rowHeight;
            $this->rule(0.3, [0.85, 0.85, 0.85]);
            $this->y -= 3;
        }

        $this->y -= 6;
    }

    private function tableHeader(array $headers, array $columnWidths): void
    {
        $x = self::MARGIN;

        foreach ($headers as $index => $header) {
            $this->writeAt($x + 2, $this->y, (string)$header, self::FONT_BOLD, 8.5, [0.3, 0.3, 0.3]);
            $x += $columnWidths[$index] ?? 0;
        }

        $this->y -= 12;
        $this->rule(0.7, [0.5, 0.5, 0.5]);
        $this->y -= 5;
    }

    /**
     * Barra de distribuição — "Proposta enviada  ████████░░  42 (28%)"
     */
    public function bar(string $label, int $value, int $total, string $suffix = ''): void
    {
        $this->ensureSpace(24);

        $percent = $total > 0 ? (int)round($value / $total * 100) : 0;
        $right = $value . ($suffix !== '' ? ' ' . $suffix : '') . ' (' . $percent . '%)';

        $rightWidth = $this->textWidth($right, self::FONT_BOLD, 8.5);

        $this->writeAt(self::MARGIN, $this->y, $label, self::FONT_REGULAR, 9, [0.15, 0.15, 0.15]);
        $this->writeAt(
            self::PAGE_WIDTH - self::MARGIN - $rightWidth,
            $this->y,
            $right,
            self::FONT_BOLD,
            8.5,
            [0.1, 0.1, 0.1]
        );

        $this->y -= 5;

        // Trilho + preenchimento
        $barHeight = 4.0;
        $filled = $this->contentWidth * ($percent / 100);

        $this->buffer .= sprintf(
            "0.90 0.90 0.90 rg %.2f %.2f %.2f %.2f re f\n",
            self::MARGIN,
            $this->y - $barHeight,
            $this->contentWidth,
            $barHeight
        );

        if ($filled > 0) {
            $this->buffer .= sprintf(
                "0.20 0.40 0.75 rg %.2f %.2f %.2f %.2f re f\n",
                self::MARGIN,
                $this->y - $barHeight,
                $filled,
                $barHeight
            );
        }

        $this->y -= ($barHeight + 9);
    }

    /**
     * Bloco monoespaçado — usado nas transcrições
     */
    public function mono(string $text, float $size = 7.6): void
    {
        $lineHeight = $size + 2.2;
        // Courier: todos os glifos têm 600/1000 de largura
        $charsPerLine = (int)floor($this->contentWidth / ($size * 0.6));

        foreach (explode("\n", $text) as $rawLine) {
            $rawLine = rtrim($rawLine);

            if ($rawLine === '') {
                $this->ensureSpace($lineHeight);
                $this->y -= $lineHeight;
                continue;
            }

            foreach ($this->hardWrap($rawLine, $charsPerLine) as $line) {
                $this->ensureSpace($lineHeight);
                $this->writeAt(self::MARGIN, $this->y, $line, self::FONT_MONO, $size, [0.15, 0.15, 0.15]);
                $this->y -= $lineHeight;
            }
        }

        $this->y -= 4;
    }

    public function rule(float $thickness = 0.5, array $color = [0.6, 0.6, 0.6]): void
    {
        $this->ensureSpace(6);

        $this->buffer .= sprintf(
            "%.2f %.2f %.2f RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
            $color[0],
            $color[1],
            $color[2],
            $thickness,
            self::MARGIN,
            $this->y,
            self::PAGE_WIDTH - self::MARGIN,
            $this->y
        );

        $this->y -= 4;
    }

    public function spacer(float $height = 8.0): void
    {
        $this->ensureSpace($height);
        $this->y -= $height;
    }

    // ------------------------------------------------------------------
    // ESCRITA DE TEXTO
    // ------------------------------------------------------------------

    private function writeLine(string $text, string $font, float $size, array $color): void
    {
        $this->ensureSpace($size + 4);
        $this->writeAt(self::MARGIN, $this->y, $text, $font, $size, $color);
        $this->y -= ($size + 4);
    }

    private function writeWrapped(string $text, string $font, float $size, array $color, float $lineHeight): void
    {
        foreach ($this->wrap($text, $font, $size, $this->contentWidth) as $line) {
            $this->ensureSpace($lineHeight);
            $this->writeAt(self::MARGIN, $this->y, $line, $font, $size, $color);
            $this->y -= $lineHeight;
        }
    }

    private function writeAt(float $x, float $y, string $text, string $font, float $size, array $color): void
    {
        if ($text === '') {
            return;
        }

        $this->buffer .= sprintf(
            "BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $size,
            $color[0],
            $color[1],
            $color[2],
            $x,
            $y,
            $this->escape($text)
        );
    }

    // ------------------------------------------------------------------
    // MEDIÇÃO E QUEBRA DE LINHA
    // ------------------------------------------------------------------

    /**
     * Quebra o texto respeitando a largura disponível
     *
     * @return string[]
     */
    private function wrap(string $text, string $font, float $size, float $maxWidth): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Margem de segurança: a tabela de larguras aproxima os acentuados
        $maxWidth *= 0.98;

        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            $words = preg_split('/ +/', trim($paragraph)) ?: [];

            if ($words === [] || $words === ['']) {
                $lines[] = '';
                continue;
            }

            $current = '';

            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;

                if ($this->textWidth($candidate, $font, $size) <= $maxWidth) {
                    $current = $candidate;
                    continue;
                }

                if ($current !== '') {
                    $lines[] = $current;
                }

                // Palavra sozinha maior que a linha (URL, telefone colado…)
                if ($this->textWidth($word, $font, $size) > $maxWidth) {
                    $chunks = $this->breakLongWord($word, $font, $size, $maxWidth);
                    $current = array_pop($chunks);
                    foreach ($chunks as $chunk) {
                        $lines[] = $chunk;
                    }
                } else {
                    $current = $word;
                }
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function breakLongWord(string $word, string $font, float $size, float $maxWidth): array
    {
        $chunks = [];
        $current = '';
        $length = mb_strlen($word, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($word, $i, 1, 'UTF-8');

            if ($this->textWidth($current . $char, $font, $size) > $maxWidth && $current !== '') {
                $chunks[] = $current;
                $current = $char;
                continue;
            }

            $current .= $char;
        }

        $chunks[] = $current;

        return $chunks;
    }

    /**
     * @return string[]
     */
    private function hardWrap(string $line, int $charsPerLine): array
    {
        if ($charsPerLine < 10) {
            $charsPerLine = 10;
        }

        if (mb_strlen($line, 'UTF-8') <= $charsPerLine) {
            return [$line];
        }

        $out = [];
        $remaining = $line;

        while (mb_strlen($remaining, 'UTF-8') > $charsPerLine) {
            $slice = mb_substr($remaining, 0, $charsPerLine, 'UTF-8');

            // Tenta cortar no último espaço para não picar palavra
            $breakAt = mb_strrpos($slice, ' ', 0, 'UTF-8');

            if ($breakAt === false || $breakAt < $charsPerLine * 0.5) {
                $breakAt = $charsPerLine;
            }

            $out[] = rtrim(mb_substr($remaining, 0, $breakAt, 'UTF-8'));
            $remaining = ltrim(mb_substr($remaining, $breakAt, null, 'UTF-8'));
        }

        if ($remaining !== '') {
            $out[] = $remaining;
        }

        return $out;
    }

    /**
     * Largura do texto em pontos
     */
    private function textWidth(string $text, string $font, float $size): float
    {
        if ($font === self::FONT_MONO) {
            return mb_strlen($text, 'UTF-8') * $size * 0.6;
        }

        $widths = $font === self::FONT_BOLD ? self::$widthsBold : self::$widthsRegular;
        $default = $font === self::FONT_BOLD ? 611 : 556;

        $total = 0;
        $length = mb_strlen($text, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $code = mb_ord(mb_substr($text, $i, 1, 'UTF-8'), 'UTF-8');
            $total += $widths[$code] ?? $default;
        }

        return ($total / 1000) * $size;
    }

    // ------------------------------------------------------------------
    // CODIFICAÇÃO
    // ------------------------------------------------------------------

    /**
     * UTF-8 -> WinAnsi (cp1252) + escape dos caracteres especiais do PDF
     */
    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);

        if ($converted === false) {
            // Fallback: remove o que não for representável
            $converted = @iconv('UTF-8', 'CP1252//IGNORE', $text) ?: '';
        }

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $converted);
    }

    // ------------------------------------------------------------------
    // SAÍDA
    // ------------------------------------------------------------------

    public function output(): string
    {
        $this->flushPage();
        $this->pageNumber = 0; // evita reflush em chamadas repetidas
        $pages = $this->pages;
        $this->pages = [];

        $pageCount = count($pages);

        // 1 catálogo + 1 pages + 3 fontes = 5 objetos fixos
        $fixedObjects = 5;
        $objects = [];

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($fixedObjects + 1 + ($i * 2)) . ' 0 R';
        }

        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>";

        $resources = "<< /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >>";

        foreach ($pages as $index => $content) {
            $pageObj = $fixedObjects + 1 + ($index * 2);
            $streamObj = $pageObj + 1;

            $objects[$pageObj] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources %s /Contents %d 0 R >>",
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $resources,
                $streamObj
            );

            $objects[$streamObj] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));

        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        // O /Info não declara encoding (o leitor assume PDFDocEncoding), então
        // aqui o título vai em ASCII puro — o texto das páginas usa WinAnsi
        $infoTitle = @iconv('UTF-8', 'ASCII//TRANSLIT', $this->title) ?: 'Relatorio';
        $infoTitle = str_replace(['\\', '(', ')'], '', $infoTitle);

        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R";
        $pdf .= " /Info << /Title (" . $infoTitle . ") /Producer (chatPerson) >> >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * Enviar como download
     */
    public function download(string $filename): void
    {
        $content = $this->output();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $content;
    }

    // ------------------------------------------------------------------
    // MÉTRICAS DAS FONTES (Helvetica / Helvetica-Bold, unidades/1000)
    // ------------------------------------------------------------------

    private static function initWidths(): void
    {
        if (!empty(self::$widthsRegular)) {
            return;
        }

        $regular = [
            278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
            1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
            333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
            556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
        ];

        $bold = [
            278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
            975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
            333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
            611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
        ];

        // Código 32 (espaço) até 126 (~)
        foreach ($regular as $index => $width) {
            self::$widthsRegular[32 + $index] = $width;
        }

        foreach ($bold as $index => $width) {
            self::$widthsBold[32 + $index] = $width;
        }

        // Acentuados do português: aproximação pela letra base
        $accentMap = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'º' => 'o', 'ª' => 'a',
        ];

        foreach ($accentMap as $accented => $base) {
            $code = mb_ord($accented, 'UTF-8');
            $baseCode = mb_ord($base, 'UTF-8');

            self::$widthsRegular[$code] = self::$widthsRegular[$baseCode] ?? 556;
            self::$widthsBold[$code] = self::$widthsBold[$baseCode] ?? 611;
        }
    }
}
