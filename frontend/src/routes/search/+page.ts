import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ url, fetch }) => {
  const q = url.searchParams.get('q') ?? '';
  const sort = url.searchParams.get('sort') ?? 'popular';
  const data: any = q ? await apiEndpoints.products(`search=${encodeURIComponent(q)}&sort=${sort}&per_page=48`, fetch) : { data: [] };
  return { q, sort, products: data.data ?? [] };
};
