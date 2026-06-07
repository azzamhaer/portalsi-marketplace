import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ fetch, url }) => {
  const params = new URLSearchParams(url.searchParams);
  if (!params.has('per_page')) params.set('per_page', '24');
  if (!params.has('page')) params.set('page', '1');
  try {
    const data: any = await apiEndpoints.products(params.toString(), fetch);
    let tags: any[] = [];
    try { tags = await apiEndpoints.tags(fetch); } catch {}
    return {
      products: data.data ?? [],
      meta: { current_page: data.current_page, last_page: data.last_page, total: data.total },
      tag: url.searchParams.get('tag') ?? '',
      sort: url.searchParams.get('sort') ?? 'popular',
      tags
    };
  } catch {
    return { products: [], meta: null, tag: '', sort: 'popular', tags: [] };
  }
};
