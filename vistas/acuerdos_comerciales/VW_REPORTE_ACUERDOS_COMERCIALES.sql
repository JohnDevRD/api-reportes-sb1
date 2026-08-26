CREATE VIEW "VW_REPORTE_ACUERDOS_COMERCIALES" AS

SELECT 
    T0."DocDate",
    T0."DocNum",
    T0."CardCode",
    T0."CardName",
    T0."DocTotal",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Dscription") AS "Dscription",
    T0."PeyMethod",
    T0."ObjType",
    'Nota de Crédito (ORPC)' AS "Origen"
FROM 
    ORPC T0
    INNER JOIN RPC1 T1 ON T0."DocEntry" = T1."DocEntry"
WHERE 
    T1."AcctCode" IN ('99000001', '99000002', '99000003')
GROUP BY 
    T0."DocDate", T0."DocNum", T0."CardCode", T0."CardName", T0."DocTotal", T0."PeyMethod", T0."ObjType"

UNION ALL

SELECT 
    T0."DocDate",
    T0."DocNum",
    CAST(NULL AS NVARCHAR(15)) AS "CardCode",
    CAST(NULL AS NVARCHAR(100)) AS "CardName",
    T0."DocTotal",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Dscription") AS "Dscription",
    CAST(NULL AS NVARCHAR(15)) AS "PeyMethod",
    T0."ObjType",
    'Entrada de Mercancía (OIGN)' AS "Origen"
FROM 
    OIGN T0
    INNER JOIN IGN1 T1 ON T0."DocEntry" = T1."DocEntry"
WHERE 
    T1."AcctCode" IN ('99000003', '99000004')
GROUP BY 
    T0."DocDate", T0."DocNum", T0."DocTotal", T0."ObjType"

UNION ALL

SELECT 
    T0."DocDate",
    T0."DocNum",
    T0."CardCode",
    T0."CardName",
    T0."DocTotal",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Descrip") AS "Dscription",
    CAST(NULL AS NVARCHAR(15)) AS "PeyMethod",
    T1."ObjType",
    'Pago Recibido (ORCT)' AS "Origen"
FROM 
    ORCT T0
    INNER JOIN RCT4 T1 ON T0."DocEntry" = T1."DocNum"
WHERE 
    T1."AcctCode" = '99000003'
GROUP BY 
    T0."DocDate", T0."DocNum", T0."CardCode", T0."CardName", T0."DocTotal", T1."ObjType";