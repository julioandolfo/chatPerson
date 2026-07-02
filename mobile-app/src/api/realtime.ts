import { client } from '@/api/client';
import type { RealtimePollRequest, RealtimePollResponse } from '@/types';

export async function poll(request: RealtimePollRequest): Promise<RealtimePollResponse> {
  const { data } = await client.post<RealtimePollResponse>('/realtime/poll', request);
  return data;
}
