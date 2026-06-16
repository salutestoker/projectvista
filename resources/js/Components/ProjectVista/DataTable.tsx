import {
    ColumnDef,
    Row,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ReactNode, useMemo } from 'react';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { cn } from '@/lib/utils';

interface DataTableProps<TData> {
    columns: ColumnDef<TData>[];
    data: TData[];
    emptyMessage?: string;
    getRowId?: (row: TData, index: number) => string;
    rowClassName?: (row: Row<TData>, index: number) => string | undefined;
    onRowClick?: (row: TData) => void;
    renderRow?: (row: Row<TData>, index: number) => ReactNode;
}

interface StyledColumnMeta {
    headerClassName?: string;
    cellClassName?: string;
}

function styledColumnMeta<TData>(
    column: ColumnDef<TData>,
): StyledColumnMeta {
    return (column.meta ?? {}) as StyledColumnMeta;
}

export function DataTable<TData>({
    columns,
    data,
    emptyMessage = 'No results.',
    getRowId,
    rowClassName,
    onRowClick,
    renderRow,
}: DataTableProps<TData>) {
    const tableData = useMemo(() => data, [data]);
    const tableColumns = useMemo(() => columns, [columns]);

    // TanStack Table intentionally returns function handles that React Compiler cannot memoize.
    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: tableData,
        columns: tableColumns,
        getCoreRowModel: getCoreRowModel(),
        getRowId,
    });
    const rows = table.getRowModel().rows;

    return (
        <Table>
            <TableHeader>
                {table.getHeaderGroups().map((headerGroup) => (
                    <TableRow key={headerGroup.id}>
                        {headerGroup.headers.map((header) => (
                            <TableHead
                                key={header.id}
                                className={
                                    styledColumnMeta(header.column.columnDef)
                                        .headerClassName
                                }
                                style={{
                                    width:
                                        header.getSize() === 150
                                            ? undefined
                                            : header.getSize(),
                                }}
                            >
                                {header.isPlaceholder
                                    ? null
                                    : flexRender(
                                          header.column.columnDef.header,
                                          header.getContext(),
                                      )}
                            </TableHead>
                        ))}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {rows.length > 0 ? (
                    rows.map((row, index) =>
                        renderRow ? (
                            renderRow(row, index)
                        ) : (
                            <TableRow
                                key={row.id}
                                className={cn(
                                    onRowClick && 'cursor-pointer',
                                    rowClassName?.(row, index),
                                )}
                                onClick={() => onRowClick?.(row.original)}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className={
                                            styledColumnMeta(
                                                cell.column.columnDef,
                                            ).cellClassName
                                        }
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ),
                    )
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={columns.length}
                            className="text-muted-foreground h-24 text-center"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
