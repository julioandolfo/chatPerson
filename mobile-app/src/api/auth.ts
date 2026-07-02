import { client } from '@/api/client';
import type { LoginData, MeData } from '@/types';

export async function login(email: string, password: string): Promise<LoginData> {
  const { data } = await client.post<LoginData>('/auth/login', { email, password });
  return data;
}

export async function getMe(): Promise<MeData> {
  const { data } = await client.get<MeData>('/auth/me');
  return data;
}
