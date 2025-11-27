import { NextRequest, NextResponse } from "next/server";
import { verifyToken, JwtPayload } from "@/lib/auth/jwt";

export interface AuthenticatedRequest extends NextRequest {
  user?: JwtPayload;
}

type RouteParams = { params?: any };

export function withAuth(
  handler: (
    req: AuthenticatedRequest,
    context: RouteParams & { user: JwtPayload }
  ) => Promise<NextResponse>
) {
  return async (req: NextRequest, context: RouteParams = {}) => {
    try {
      const authHeader = req.headers.get("authorization");

      if (!authHeader || !authHeader.startsWith("Bearer ")) {
        return NextResponse.json(
          { message: "Missing or invalid authorization header" },
          { status: 401 }
        );
      }

      const token = authHeader.substring(7);
      const payload = verifyToken(token);

      // Attach user to request
      const authenticatedReq = req as AuthenticatedRequest;
      authenticatedReq.user = payload;

      return handler(authenticatedReq, { ...context, user: payload });
    } catch (error) {
      return NextResponse.json(
        { message: "Unauthorized", error: (error as Error).message },
        { status: 401 }
      );
    }
  };
}
