grant usage on schema public to anon, authenticated;
grant all on public.repair_orders to anon, authenticated;
grant all on public.spare_parts to anon, authenticated;
grant usage, select on sequence public.repair_orders_id_seq to anon, authenticated;
grant usage, select on sequence public.spare_parts_id_seq to anon, authenticated;

alter table public.repair_orders enable row level security;
alter table public.spare_parts enable row level security;

drop policy if exists "repair_orders_public_access" on public.repair_orders;
create policy "repair_orders_public_access"
on public.repair_orders
for all
to anon, authenticated
using (true)
with check (true);

drop policy if exists "spare_parts_public_access" on public.spare_parts;
create policy "spare_parts_public_access"
on public.spare_parts
for all
to anon, authenticated
using (true)
with check (true);
