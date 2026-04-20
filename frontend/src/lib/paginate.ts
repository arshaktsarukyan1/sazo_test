type LaravelPage<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
};

export async function fetchAllPages<T>(
  fetchPage: (page: number) => Promise<LaravelPage<T>>,
): Promise<T[]> {
  const out: T[] = [];
  let page = 1;
  let last = 1;

  do {
    const chunk = await fetchPage(page);
    out.push(...chunk.data);
    last = chunk.last_page;
    page += 1;
  } while (page <= last);

  return out;
}
