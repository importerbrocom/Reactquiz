/**
 * Standard API envelope — every response uses this shape.
 */
export interface ApiEnvelope<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors: ApiFieldErrors | null;
  meta: Record<string, unknown> | null;
}

/** Validation errors keyed by field name */
export interface ApiFieldErrors {
  [field: string]: string[];
}

/** Normalised error thrown by the error interceptor */
export interface ApiError {
  status: number;
  message: string;
  code: string | null;
  errors: ApiFieldErrors | null;
  meta: Record<string, unknown> | null;
}

/** Offset-based pagination envelope */
export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/** Cursor-based pagination envelope */
export interface CursorPaginated<T> {
  data: T[];
  next_cursor: string | null;
  prev_cursor: string | null;
  has_more: boolean;
}

/** Standard list response (non-paginated) */
export type ListResponse<T> = ApiEnvelope<T[]>;

/** Standard single-item response */
export type ItemResponse<T> = ApiEnvelope<T>;
