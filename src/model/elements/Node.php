<?php

namespace FluidGraph;

use RuntimeException;

abstract class Node extends Element
{
	/**
	 * Attach one or more labels to the node
	 */
	public function label(string ...$labels): static
	{
		$content = $this->contentOr(RuntimeException::class, sprintf(
			'Cannot label element prior to it being fastened'
		));

		foreach ($labels as $label) {
			if (!isset($content->labels[$label])) {
				$content->labels[$label] = Status::INDUCTED;
				continue;
			}

			if ($content->labels[$label] == Status::RELEASED) {
				$content->labels[$label] = Status::ATTACHED;
				continue;
			}

			if ($content->labels[$label] == Status::DETACHED) {
				$content->labels[$label] = Status::INDUCTED;
				continue;
			}
		}

		return $this;
	}


	/**
	 *
	 */
	public function like(string ...$labels): bool
	{
		$content = $this->contentOr(RuntimeException::class, sprintf(
			'Cannot compare labels on element prior to it being fastened'
		));

		$intersection = array_intersect($labels, array_keys($content->labels));

		if (count($intersection) == count($labels)) {
			return TRUE;
		}

		return FALSE;
	}


	/**
	 *
	 */
	public function likeAny(string ...$labels): bool
	{
		$content = $this->contentOr(RuntimeException::class, sprintf(
			'Cannot compare labels on element prior to it being fastened'
		));

		$intersection = array_intersect($labels, array_keys($content->labels));

		if (count($intersection)) {
			return TRUE;
		}

		return FALSE;
	}


	/**
	 * Remove one or more labels from the element
	 */
	public function unlabel(string ...$labels): static
	{
		$content = $this->contentOr(RuntimeException::class, sprintf(
			'Cannot unlabel element prior to it being fastened'
		));

		foreach ($labels as $labels) {
			if (!isset($content->labels[$labels])) {
				continue;
			}

			if ($content->labels[$labels] == Status::ATTACHED) {
				$content->labels[$labels] = Status::RELEASED;
				continue;
			}

			if (in_array($content->labels[$labels], [Status::FASTENED, Status::INDUCTED])) {
				unset($content->labels[$labels]);
				continue;
			}
		}

		return $this;
	}
}
