<?php

declare(strict_types=1);

namespace ElliePHP\Components\Routing\Core;

use Throwable;

class HtmlErrorFormatter implements ErrorFormatterInterface
{
    public function format(Throwable $e, bool $debugMode): array
    {
        $status = $e->getCode();
        $status = $status >= 100 && $status < 600 ? $status : 500;

        $title = $debugMode ? get_class($e) : $this->getStatusMessage($status);
        $message = $debugMode ? $e->getMessage() : 'Something went wrong on our servers.';

        $html = $debugMode
            ? $this->renderDebug($e, $status, $title, $message)
            : $this->renderProduction($status, $title, $message);

        return ['html' => $html, 'status' => $status];
    }

    private function renderDebug(Throwable $e, int $status, string $title, string $message): string
    {
        $traceHtml = $this->renderTrace($e->getTrace());
        $codeSnippet = $this->renderSourceCode($e->getFile(), $e->getLine());
        $class = get_class($e);
        $file = $e->getFile();
        $line = $e->getLine();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error: {$message}</title>
    <style>
        :root {
            --bg-body: #0d1117;
            --bg-card: #161b22;
            --bg-code: #0d1117;
            --bg-highlight: #3c1618;
            --border: #30363d;
            --text-main: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #f85149;
            
            /* VS Code Dark / One Dark Colors */
            --c-kwd: #ff7b72;      /* Keywords (red/pink) */
            --c-fn: #d2a8ff;       /* Functions (purple) */
            --c-str: #a5d6ff;      /* Strings (light blue) */
            --c-var: #79c0ff;      /* Variables (blue) */
            --c-num: #79c0ff;      /* Numbers */
            --c-com: #8b949e;      /* Comments (gray) */
            --c-cls: #f0883e;      /* Classes (orange) */
        }
        
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 2rem; line-height: 1.5; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .header { margin-bottom: 2rem; border-left: 5px solid var(--accent); padding-left: 1.5rem; }
        .meta { color: var(--text-muted); font-size: 0.85rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .exc-title { font-size: 1.75rem; font-weight: 600; margin-top: 0.5rem; color: var(--text-main); word-break: break-word; }

        /* Card */
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .card-head { background: #21262d; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .card-title { font-weight: 600; font-size: 0.95rem; }
        .file-loc { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.85rem; color: var(--text-muted); }

        /* Code Viewer */
        .code-box { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 14px; background: var(--bg-code); overflow-x: auto; line-height: 1.6; }
        .row { display: flex; }
        .row.active { background: var(--bg-highlight); }
        .num { flex: 0 0 50px; text-align: right; padding-right: 15px; color: #484f58; user-select: none; border-right: 1px solid var(--border); }
        .src { padding-left: 15px; white-space: pre; color: var(--text-main); }

        /* Syntax Colors */
        .kwd { color: var(--c-kwd); }
        .fn  { color: var(--c-fn); }
        .str { color: var(--c-str); }
        .var { color: var(--c-var); }
        .num { color: var(--c-num); }
        .com { color: var(--c-com); font-style: italic; }
        .cls { color: var(--c-cls); }

        /* Stack Trace */
        .trace { list-style: none; padding: 0; margin: 0; }
        .trace li { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; font-size: 0.9rem; }
        .trace li:last-child { border-bottom: none; }
        .trace-fn { font-family: monospace; color: var(--c-fn); font-weight: 600; margin-bottom: 2px; }
        .trace-file { color: var(--text-muted); font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="meta">{$class} &bull; {$status}</div>
            <div class="exc-title">{$message}</div>
        </div>

        <div class="card">
            <div class="card-head">
                <span class="card-title">Source Code</span>
                <span class="file-loc">{$file} : {$line}</span>
            </div>
            <div class="code-box">
                {$codeSnippet}
            </div>
        </div>

        <div class="card">
            <div class="card-head"><span class="card-title">Stack Trace</span></div>
            <ul class="trace">{$traceHtml}</ul>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Renders code with safe syntax highlighting (Single Pass Regex).
     */
    private function renderSourceCode(string $file, int $errorLine): string
    {
        if (!file_exists($file) || !is_readable($file)) {
            return '<div style="padding:1rem">File unavailable</div>';
        }

        $lines = file($file);
        $totalLines = count($lines);
        $start = max(0, $errorLine - 10);
        $end = min($totalLines, $errorLine + 10);
        $html = '';

        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            // 1. Escape HTML first (converts < to &lt;, " to &quot;)
            $rawLine = htmlspecialchars($lines[$i]);

            // 2. Apply highlighting in ONE pass to avoid collisions
            $highlighted = $this->highlightSyntax($rawLine);

            $activeClass = ($lineNum === $errorLine) ? 'active' : '';

            $html .= "<div class='row {$activeClass}'>";
            $html .= "<div class='num'>{$lineNum}</div>";
            $html .= "<div class='src'>{$highlighted}</div>";
            $html .= "</div>";
        }

        return $html;
    }

    /**
     * Single-pass regex to safely highlight escaped PHP code.
     */
    private function highlightSyntax(string $line): string
    {
        // Patterns for escaped text
        $patterns = [
            'comment'  => '(\/\/.*|#.*)',                                // Matches // or # comments
            'string'   => '(&quot;.*?&quot;|\'[^\']*\')',                 // Matches "..." (escaped) or '...'
            'variable' => '(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)', // Matches $var
            'function' => '\b([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*(?=\()', // Matches func(
            'keyword'  => '\b(namespace|use|class|implements|extends|function|public|private|protected|return|if|else|foreach|as|try|catch|throw|new|static|const|declare|echo|print)\b',
        ];

        // Combine all patterns into one Regex with capture groups
        // 1=Comment, 2=String, 3=Variable, 4=Function, 5=Keyword
        $regex = "/{$patterns['comment']}|{$patterns['string']}|{$patterns['variable']}|{$patterns['function']}|({$patterns['keyword']})/";

        return preg_replace_callback($regex, function ($matches) {
            if (!empty($matches[1])) return '<span class="com">' . $matches[1] . '</span>'; // Comment
            if (!empty($matches[2])) return '<span class="str">' . $matches[2] . '</span>'; // String
            if (!empty($matches[3])) return '<span class="var">' . $matches[3] . '</span>'; // Variable
            if (!empty($matches[4])) return '<span class="fn">' . $matches[4] . '</span>';  // Function
            if (!empty($matches[5])) return '<span class="kwd">' . $matches[5] . '</span>'; // Keyword
            return $matches[0];
        }, $line);
    }

    private function renderTrace(array $trace): string
    {
        $html = '';
        foreach ($trace as $frame) {
            $fn = $frame['function'] ?? '';
            $cls = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '';

            $call = $cls ? "{$cls}{$type}{$fn}()" : "{$fn}()";

            $html .= "<li>";
            $html .= "<div class='trace-fn'>{$call}</div>";
            if ($file !== '[internal]') {
                $html .= "<div class='trace-file'>{$file}:{$line}</div>";
            }
            $html .= "</li>";
        }
        return $html;
    }

    private function renderProduction(int $status, string $title, string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: #f7fafc; color: #4a5568; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; display: flex; align-items: center; }
        .code { font-size: 3rem; border-right: 2px solid #cbd5e0; padding-right: 1.5rem; margin-right: 1.5rem; color: #718096; }
        .message { font-size: 1.25rem; text-transform: uppercase; letter-spacing: 0.1em; color: #718096; }
        @media(max-width:600px) { .container { flex-direction: column; } .code { border: none; border-bottom: 2px solid #ccc; padding: 0 0 1rem 0; margin: 0 0 1rem 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">{$status}</div>
        <div class="message">{$title}</div>
    </div>
</body>
</html>
HTML;
    }

    private function getStatusMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
            404 => 'Not Found', 419 => 'Page Expired', 429 => 'Too Many Requests',
            500 => 'Server Error', 503 => 'Service Unavailable', default => 'Error',
        };
    }
}