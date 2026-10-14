<?php
final class PloggerPluralNode {
    public function __construct(public string $kind, public string $operator,
        public ?self $left = null, public ?self $right = null, public ?self $condition = null) {}
}
/** Bounded interpreter for GNU gettext's integer plural-expression grammar. */
final class PloggerPluralExpression {
    /** @var list<string> */
    private array $tokens = [];
    private int $position = 0;
    private const PRECEDENCE = ['||' => 1, '&&' => 2, '==' => 3, '!=' => 3,
        '<' => 4, '<=' => 4, '>' => 4, '>=' => 4, '+' => 5, '-' => 5,
        '*' => 6, '/' => 6, '%' => 6];

    public static function select(string $header, int $number): int {
        if (strlen($header) > 4096 || !preg_match('/\A\s*nplurals\s*=\s*([0-9]{1,3})\s*;\s*plural\s*=\s*(.*?)\s*;?\s*\z/s', $header, $match)) return $number === 1 ? 0 : 1;
        $total = (int) $match[1];
        if ($total < 1 || $total > 100) return $number === 1 ? 0 : 1;
        try {
            $parser = new self();
            $expression = $match[2];
            $offset = 0;
            while ($offset < strlen($expression)) {
                if (!preg_match('/\G\s*(\d{1,10}|n|&&|\|\||==|!=|<=|>=|[!+*\/%<>?:()\-])\s*/', $expression, $token, 0, $offset)) throw new InvalidArgumentException();
                $parser->tokens[] = $token[1];
                $offset += strlen($token[0]);
                if (count($parser->tokens) > 512) throw new InvalidArgumentException();
            }
            $tree = $parser->parse(0, 0);
            if ($parser->position !== count($parser->tokens)) throw new InvalidArgumentException();
            $value = $parser->value($tree, $number);
            return $value >= 0 && $value < $total ? $value : 0;
        } catch (InvalidArgumentException) { return $number === 1 ? 0 : 1; }
    }

    private function take(): string {
        if ($this->position >= count($this->tokens)) throw new InvalidArgumentException();
        return $this->tokens[$this->position++];
    }

    private function parse(int $minimum, int $depth): PloggerPluralNode {
        if ($depth > 64) throw new InvalidArgumentException();
        $token = $this->take();
        if ($token === '(') {
            $left = $this->parse(0, $depth + 1);
            if ($this->take() !== ')') throw new InvalidArgumentException();
        } elseif (in_array($token, ['!', '-', '+'], true)) {
            $left = new PloggerPluralNode('unary', $token, $this->parse(7, $depth + 1));
        } elseif ($token === 'n' || ctype_digit($token)) {
            $left = new PloggerPluralNode('literal', $token);
        } else { throw new InvalidArgumentException(); }
        while (true) {
            $operator = $this->tokens[$this->position] ?? '';
            $precedence = self::PRECEDENCE[$operator] ?? -1;
            if ($precedence < $minimum) break;
            ++$this->position;
            $left = new PloggerPluralNode('binary', $operator, $left, $this->parse($precedence + 1, $depth + 1));
        }
        if ($minimum === 0 && ($this->tokens[$this->position] ?? '') === '?') {
            ++$this->position;
            $yes = $this->parse(0, $depth + 1);
            if ($this->take() !== ':') throw new InvalidArgumentException();
            $left = new PloggerPluralNode('conditional', '', $yes, $this->parse(0, $depth + 1), $left);
        }
        return $left;
    }

    private function value(PloggerPluralNode $node, int $number): int {
        if ($node->kind === 'literal') return $node->operator === 'n' ? $number : (int) $node->operator;
        if ($node->left === null) throw new InvalidArgumentException();
        if ($node->kind === 'conditional') {
            if ($node->condition === null || $node->right === null) throw new InvalidArgumentException();
            return $this->value($this->value($node->condition, $number) ? $node->left : $node->right, $number);
        }
        $left = $this->value($node->left, $number);
        if ($node->kind === 'unary') return match ($node->operator) { '!' => (int) !$left, '-' => (int) -$left, default => $left };
        if ($node->right === null) throw new InvalidArgumentException();
        if ($node->operator === '&&' && !$left) return 0;
        if ($node->operator === '||' && $left) return 1;
        $right = $this->value($node->right, $number);
        return match ($node->operator) {
            '||' => (int) ((bool) $left || (bool) $right), '&&' => (int) ((bool) $left && (bool) $right),
            '==' => (int) ($left === $right), '!=' => (int) ($left !== $right),
            '<' => (int) ($left < $right), '<=' => (int) ($left <= $right),
            '>' => (int) ($left > $right), '>=' => (int) ($left >= $right),
            '+' => (int) ($left + $right), '-' => (int) ($left - $right), '*' => (int) ($left * $right),
            '/' => $right === 0 ? 0 : (int) ($left / $right), '%' => $right === 0 ? 0 : $left % $right,
            default => throw new InvalidArgumentException(),
        };
    }
}
