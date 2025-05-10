<?php

namespace FluidGraph;

use RuntimeException;
use Twig\Error\RuntimeError;

abstract class Edge extends Element
{
	/**
	 *
	 */
	public function for(Content\Node|Node|string $node): bool
	{
		$content = $this->contentOr(
			RuntimeException::class,
			'Cannot determine edge target prior to being fastened'
		);

		if (is_null($content->target)) {
			return FALSE;
		}

		if (is_string($node)) {
			if (!isset($content->target->labels[$node])) {
				return FALSE;
			}

			return in_array($content->target->labels[$node], [
				Status::FASTENED,
				Status::INDUCTED,
				Status::ATTACHED
			]);
		}

		if ($node instanceof Node) {
			$node = $node->contentOr(
				RuntimeError::class,
				'Cannot determine edge target on node, not fastened'
			);
		}

		return $node === $content->target;
	}
}
